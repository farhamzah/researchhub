<?php

namespace App\Modules\Surveys\Services;

use App\Models\Survey;
use App\Models\SurveyIndicator;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SurveyBulkQuestionImportService
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Survey $survey, string $input, string $indicatorStrategy = 'create'): array
    {
        $payload = $this->parse($input);
        $this->validatePayload($survey, $payload, $indicatorStrategy, false);

        $indicatorName = $payload['defaults']['indicator'] ?? null;
        $indicatorMatch = $this->indicatorMatch($survey, $indicatorName);
        $indicator = $indicatorMatch['exact'];
        $existingKeys = $this->existingQuestionKeys($survey, $payload);
        $duplicateKeys = $this->duplicateImportKeys($payload);
        $newIndicator = $indicatorName && ! $indicator && $indicatorMatch['near'] === null && $indicatorStrategy === 'create'
            ? (string) $indicatorName
            : null;

        return [
            'page' => $payload['page'],
            'page_exists' => $this->pageExists($survey, $payload['page']['title']),
            'indicator' => $indicatorName,
            'indicator_exists' => $indicator !== null,
            'indicator_match' => $indicator?->name,
            'existing_indicators_used' => $indicator ? [$indicator->name] : [],
            'new_indicators_to_create' => $newIndicator ? [$newIndicator] : [],
            'possible_duplicate_indicators' => $indicatorMatch['near'] ? [$indicatorMatch['near']->name] : [],
            'indicator_strategy' => $indicatorStrategy,
            'questions' => $payload['questions'],
            'question_count' => count($payload['questions']),
            'duplicate_keys' => $duplicateKeys->merge($existingKeys)->unique()->values()->all(),
            'question_type' => $payload['defaults']['type'],
            'required' => (bool) $payload['defaults']['required'],
            'scoring' => $this->previewScoringRows($payload, $indicator !== null || $newIndicator !== null),
            'warnings' => $this->warnings($payload, $indicatorMatch, $indicatorStrategy, $existingKeys, $duplicateKeys),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function import(User $user, Survey $survey, string $input, string $indicatorStrategy = 'create'): array
    {
        $payload = $this->parse($input);
        $this->validatePayload($survey, $payload, $indicatorStrategy, true);

        return DB::transaction(function () use ($user, $survey, $payload, $indicatorStrategy): array {
            $page = $this->page($survey, $payload['page']);
            $indicator = $this->indicator($survey, $payload, $indicatorStrategy);
            $sortOrder = max((int) $survey->questions()->max('sort_order'), 0);
            $created = [];

            foreach ($payload['questions'] as $questionData) {
                $sortOrder++;
                $question = $survey->questions()->create([
                    'page_id' => $page->id,
                    'question_key' => $questionData['key'],
                    'type' => $payload['defaults']['type'],
                    'label' => $questionData['text'],
                    'help_text' => $payload['defaults']['help_text'] ?? null,
                    'options' => $this->optionsFor($payload),
                    'settings' => $this->settingsFor($payload),
                    'is_required' => (bool) ($payload['defaults']['required'] ?? false),
                    'sort_order' => $sortOrder,
                ]);

                if ($indicator instanceof SurveyIndicator && $payload['defaults']['type'] === SurveyQuestion::TYPE_LIKERT) {
                    $question->scoring()->create([
                        'survey_id' => $survey->id,
                        'survey_indicator_id' => $indicator->id,
                        'is_scored' => true,
                        'score_min' => $payload['defaults']['min'] ?? 1,
                        'score_max' => $payload['defaults']['max'] ?? 5,
                        'weight' => $payload['defaults']['weight'] ?? 1,
                        'is_reverse_scored' => false,
                        'settings' => null,
                    ]);
                }

                $created[] = $question;
            }

            $this->activityLogger->log('survey.bulk_questions_imported', $user, $survey->project, $survey, [
                'survey_id' => $survey->id,
                'page_id' => $page->id,
                'question_count' => count($created),
                'indicator_id' => $indicator?->id,
            ]);

            return [
                'page' => $page,
                'indicator' => $indicator,
                'question_count' => count($created),
                'question_keys' => collect($created)->pluck('question_key')->all(),
            ];
        });
    }

    /**
     * @return array{page: array<string, mixed>, defaults: array<string, mixed>, questions: array<int, array{key: string, text: string}>}
     */
    private function parse(string $input): array
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            throw ValidationException::withMessages(['bulk_input' => 'Bulk input cannot be empty.']);
        }

        if (str_starts_with($trimmed, '{')) {
            return $this->parseJson($trimmed);
        }

        return $this->parseText($trimmed);
    }

    /**
     * @return array{page: array<string, mixed>, defaults: array<string, mixed>, questions: array<int, array{key: string, text: string}>}
     */
    private function parseJson(string $input): array
    {
        try {
            $decoded = json_decode($input, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['bulk_input' => 'Bulk JSON is not valid.']);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['bulk_input' => 'Bulk JSON must be an object.']);
        }

        $questions = collect($decoded['questions'] ?? [])
            ->map(fn (mixed $question): array => [
                'key' => trim((string) ($question['key'] ?? '')),
                'text' => trim((string) ($question['text'] ?? $question['label'] ?? '')),
            ])
            ->filter(fn (array $question): bool => $question['key'] !== '' && $question['text'] !== '')
            ->values()
            ->all();

        return [
            'page' => [
                'title' => trim((string) data_get($decoded, 'page.title', 'Imported Questions')),
                'description' => trim((string) data_get($decoded, 'page.description', '')),
                'order' => (int) data_get($decoded, 'page.order', 0),
            ],
            'defaults' => $this->normalizeDefaults((array) ($decoded['defaults'] ?? [])),
            'questions' => $questions,
        ];
    }

    /**
     * @return array{page: array<string, mixed>, defaults: array<string, mixed>, questions: array<int, array{key: string, text: string}>}
     */
    private function parseText(string $input): array
    {
        $headers = [];
        $questions = [];

        foreach (preg_split('/\r\n|\r|\n/', $input) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_contains($line, '|')) {
                [$key, $text] = array_map('trim', explode('|', $line, 2));
                $questions[] = ['key' => $key, 'text' => $text];

                continue;
            }

            if (str_contains($line, ':')) {
                [$header, $value] = array_map('trim', explode(':', $line, 2));
                $headers[strtolower($header)] = $value;
            }
        }

        return [
            'page' => [
                'title' => $headers['page'] ?? 'Imported Questions',
                'description' => $headers['page_description'] ?? '',
                'order' => (int) ($headers['page_order'] ?? 0),
            ],
            'defaults' => $this->normalizeDefaults([
                'type' => $headers['type'] ?? SurveyQuestion::TYPE_LIKERT,
                'required' => $headers['required'] ?? false,
                'indicator' => $headers['indicator'] ?? null,
                'scale' => $headers['scale'] ?? [1, 2, 3, 4, 5],
                'min' => $headers['min'] ?? 1,
                'max' => $headers['max'] ?? 5,
                'weight' => $headers['weight'] ?? 1,
                'help_text' => $headers['help'] ?? null,
            ]),
            'questions' => $questions,
        ];
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function normalizeDefaults(array $defaults): array
    {
        $type = Str::of((string) ($defaults['type'] ?? SurveyQuestion::TYPE_LIKERT))->lower()->replace(' ', '_')->toString();

        if ($type === 'likert') {
            $type = SurveyQuestion::TYPE_LIKERT;
        }

        $scale = $defaults['scale'] ?? [1, 2, 3, 4, 5];
        if (is_string($scale)) {
            $scale = array_map('trim', explode(',', $scale));
        }

        return [
            'type' => $type,
            'required' => filter_var($defaults['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'indicator' => filled($defaults['indicator'] ?? null) ? (string) $defaults['indicator'] : null,
            'scale' => array_values(array_filter(is_array($scale) ? $scale : [1, 2, 3, 4, 5], fn (mixed $value): bool => $value !== '')),
            'min' => is_numeric($defaults['min'] ?? null) ? (float) $defaults['min'] : 1,
            'max' => is_numeric($defaults['max'] ?? null) ? (float) $defaults['max'] : 5,
            'weight' => is_numeric($defaults['weight'] ?? null) ? (float) $defaults['weight'] : 1,
            'help_text' => filled($defaults['help_text'] ?? null) ? (string) $defaults['help_text'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validatePayload(Survey $survey, array $payload, string $indicatorStrategy, bool $forImport = true): void
    {
        if (! in_array($indicatorStrategy, ['create', 'skip', 'cancel'], true)) {
            throw ValidationException::withMessages(['indicator_strategy' => 'Choose create, skip, or cancel for missing indicators.']);
        }

        if (blank($payload['page']['title'] ?? null)) {
            throw ValidationException::withMessages(['bulk_input' => 'Bulk import needs a page title.']);
        }

        if (($payload['questions'] ?? []) === []) {
            throw ValidationException::withMessages(['bulk_input' => 'Bulk import needs at least one question row.']);
        }

        $duplicateKeys = $this->duplicateImportKeys($payload);

        if ($forImport && $duplicateKeys->isNotEmpty()) {
            throw ValidationException::withMessages(['bulk_input' => 'Duplicate question keys in import: '.$duplicateKeys->join(', ')]);
        }

        $existingKeys = $this->existingQuestionKeys($survey, $payload);
        if ($forImport && $existingKeys->isNotEmpty()) {
            throw ValidationException::withMessages(['bulk_input' => 'Question keys already exist: '.$existingKeys->join(', ')]);
        }

        if (! in_array($payload['defaults']['type'], config('researchhub_surveys.question_types', []), true)) {
            throw ValidationException::withMessages(['bulk_input' => 'Unsupported question type: '.$payload['defaults']['type']]);
        }

        if ($payload['defaults']['indicator'] && $indicatorStrategy === 'cancel') {
            $match = $this->indicatorMatch($survey, $payload['defaults']['indicator']);
            if (! $match['exact']) {
                throw ValidationException::withMessages(['indicator_strategy' => 'Indicator does not exist. Choose create or skip to continue.']);
            }
        }

        if ($forImport && $payload['defaults']['indicator'] && $indicatorStrategy === 'create') {
            $match = $this->indicatorMatch($survey, $payload['defaults']['indicator']);
            if (! $match['exact'] && $match['near']) {
                throw ValidationException::withMessages([
                    'indicator_strategy' => 'Possible existing indicator found: '.$match['near']->name.'. Use that existing indicator name exactly, or choose skip if this import should be descriptive.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function page(Survey $survey, array $page): SurveyPage
    {
        return $survey->pages()->firstOrCreate(
            ['title' => $page['title']],
            [
                'description' => $page['description'] ?: null,
                'sort_order' => (int) ($page['order'] ?: ($survey->pages()->max('sort_order') + 1)),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function indicator(Survey $survey, array $payload, string $indicatorStrategy): ?SurveyIndicator
    {
        $indicatorName = $payload['defaults']['indicator'] ?? null;
        if (! $indicatorName) {
            return null;
        }

        $match = $this->indicatorMatch($survey, $indicatorName);
        $indicator = $match['exact'];
        if ($indicator instanceof SurveyIndicator || $indicatorStrategy === 'skip') {
            return $indicator;
        }

        return $survey->indicators()->create([
            'name' => (string) $indicatorName,
            'slug' => Str::slug((string) $indicatorName) ?: 'bulk-indicator',
            'description' => 'Created from bulk question import.',
            'sort_order' => (int) $survey->indicators()->max('sort_order') + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function optionsFor(array $payload): ?array
    {
        return match ($payload['defaults']['type']) {
            SurveyQuestion::TYPE_LIKERT_MATRIX => [
                'rows' => collect($payload['questions'])->pluck('text')->values()->all(),
                'columns' => $this->scaleColumns($payload['defaults']['scale']),
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function settingsFor(array $payload): ?array
    {
        return match ($payload['defaults']['type']) {
            SurveyQuestion::TYPE_LIKERT => ['scale' => $payload['defaults']['scale']],
            default => null,
        };
    }

    /**
     * @param  array<int, mixed>  $scale
     * @return array<int, array{value: string, label: string}>
     */
    private function scaleColumns(array $scale): array
    {
        return collect($scale)
            ->values()
            ->map(fn (mixed $value): array => ['value' => (string) $value, 'label' => (string) $value])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function previewScoringRows(array $payload, bool $hasIndicator): array
    {
        return collect($payload['questions'])
            ->map(fn (array $question): array => [
                'key' => $question['key'],
                'indicator' => $payload['defaults']['indicator'],
                'status' => $payload['defaults']['type'] === SurveyQuestion::TYPE_LIKERT && $hasIndicator ? 'Configured' : 'Descriptive',
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{exact: ?SurveyIndicator, near: ?SurveyIndicator}  $indicatorMatch
     * @param  Collection<int, string>  $existingKeys
     * @param  Collection<int, string>  $duplicateKeys
     * @return array<int, string>
     */
    private function warnings(array $payload, array $indicatorMatch, string $indicatorStrategy, Collection $existingKeys, Collection $duplicateKeys): array
    {
        $warnings = [];

        if (($payload['defaults']['indicator'] ?? null) && ! $indicatorMatch['exact']) {
            if ($indicatorMatch['near']) {
                $warnings[] = 'Possible existing indicator found: '.$indicatorMatch['near']->name.'. Use existing instead of creating new?';
            }

            $warnings[] = $indicatorStrategy === 'create'
                ? ($indicatorMatch['near'] ? 'Import is blocked until the indicator name is corrected or indicator linking is skipped.' : 'New indicator will be created before questions are imported.')
                : 'Questions will be imported without an indicator link.';
        }

        if ($indicatorMatch['exact']) {
            $warnings[] = 'Existing indicator will be reused: '.$indicatorMatch['exact']->name.'.';
        }

        if ($existingKeys->isNotEmpty()) {
            $warnings[] = 'Question keys already exist and must be removed before import: '.$existingKeys->join(', ').'.';
        }

        if ($duplicateKeys->isNotEmpty()) {
            $warnings[] = 'Duplicate question keys inside this import: '.$duplicateKeys->join(', ').'.';
        }

        if ($payload['defaults']['type'] !== SurveyQuestion::TYPE_LIKERT) {
            $warnings[] = 'Only Likert bulk rows are auto-scored in this importer. Other types are imported as descriptive questions.';
        }

        return $warnings;
    }

    private function pageExists(Survey $survey, string $title): bool
    {
        return $survey->pages->contains(fn (SurveyPage $page): bool => $this->normalizeName((string) $page->title) === $this->normalizeName($title));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, string>
     */
    private function duplicateImportKeys(array $payload): Collection
    {
        return collect($payload['questions'])
            ->pluck('key')
            ->map(fn (string $key): string => trim($key))
            ->filter()
            ->duplicates()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, string>
     */
    private function existingQuestionKeys(Survey $survey, array $payload): Collection
    {
        $keys = collect($payload['questions'])->pluck('key')->map(fn (string $key): string => trim($key))->filter();

        return $survey->questions()->whereIn('question_key', $keys->all())->pluck('question_key');
    }

    /**
     * @return array{exact: ?SurveyIndicator, near: ?SurveyIndicator}
     */
    private function indicatorMatch(Survey $survey, mixed $name): array
    {
        if (! filled($name)) {
            return ['exact' => null, 'near' => null];
        }

        $normalized = $this->normalizeName((string) $name);
        $indicators = $survey->relationLoaded('indicators') ? $survey->indicators : $survey->indicators()->get();
        $exact = $indicators->first(fn (SurveyIndicator $indicator): bool => $this->normalizeName($indicator->name) === $normalized);

        if ($exact instanceof SurveyIndicator) {
            return ['exact' => $exact, 'near' => null];
        }

        $near = $indicators
            ->map(function (SurveyIndicator $indicator) use ($normalized): array {
                similar_text($normalized, $this->normalizeName($indicator->name), $percent);

                return ['indicator' => $indicator, 'percent' => $percent];
            })
            ->sortByDesc('percent')
            ->first();

        return [
            'exact' => null,
            'near' => $near && $near['percent'] >= 82 ? $near['indicator'] : null,
        ];
    }

    private function normalizeName(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/\s*\/\s*/', '/')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
