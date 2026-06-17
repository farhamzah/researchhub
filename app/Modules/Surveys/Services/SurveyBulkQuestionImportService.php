<?php

namespace App\Modules\Surveys\Services;

use App\Models\Survey;
use App\Models\SurveyIndicator;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
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
        $this->validatePayload($survey, $payload, $indicatorStrategy);

        $indicatorName = $payload['defaults']['indicator'] ?? null;
        $indicator = $indicatorName
            ? $survey->indicators->first(fn (SurveyIndicator $indicator): bool => strcasecmp($indicator->name, (string) $indicatorName) === 0)
            : null;

        return [
            'page' => $payload['page'],
            'page_exists' => $survey->pages->contains(fn (SurveyPage $page): bool => strcasecmp((string) $page->title, (string) $payload['page']['title']) === 0),
            'indicator' => $indicatorName,
            'indicator_exists' => $indicator !== null,
            'indicator_strategy' => $indicatorStrategy,
            'questions' => $payload['questions'],
            'question_count' => count($payload['questions']),
            'scoring' => $this->previewScoringRows($payload, $indicator !== null || $indicatorStrategy === 'create'),
            'warnings' => $this->warnings($payload, $indicator !== null, $indicatorStrategy),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function import(User $user, Survey $survey, string $input, string $indicatorStrategy = 'create'): array
    {
        $payload = $this->parse($input);
        $this->validatePayload($survey, $payload, $indicatorStrategy);

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
    private function validatePayload(Survey $survey, array $payload, string $indicatorStrategy): void
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

        $keys = collect($payload['questions'])->pluck('key')->map(fn (string $key): string => trim($key))->filter();
        $duplicateKeys = $keys->duplicates()->values();

        if ($duplicateKeys->isNotEmpty()) {
            throw ValidationException::withMessages(['bulk_input' => 'Duplicate question keys in import: '.$duplicateKeys->join(', ')]);
        }

        $existingKeys = $survey->questions()->whereIn('question_key', $keys->all())->pluck('question_key');
        if ($existingKeys->isNotEmpty()) {
            throw ValidationException::withMessages(['bulk_input' => 'Question keys already exist: '.$existingKeys->join(', ')]);
        }

        if (! in_array($payload['defaults']['type'], config('researchhub_surveys.question_types', []), true)) {
            throw ValidationException::withMessages(['bulk_input' => 'Unsupported question type: '.$payload['defaults']['type']]);
        }

        if ($payload['defaults']['indicator'] && $indicatorStrategy === 'cancel') {
            $exists = $survey->indicators->contains(fn (SurveyIndicator $indicator): bool => strcasecmp($indicator->name, (string) $payload['defaults']['indicator']) === 0);
            if (! $exists) {
                throw ValidationException::withMessages(['indicator_strategy' => 'Indicator does not exist. Choose create or skip to continue.']);
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

        $indicator = $survey->indicators()->whereRaw('lower(name) = ?', [mb_strtolower((string) $indicatorName)])->first();
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
     * @return array<int, string>
     */
    private function warnings(array $payload, bool $indicatorExists, string $indicatorStrategy): array
    {
        $warnings = [];

        if (($payload['defaults']['indicator'] ?? null) && ! $indicatorExists) {
            $warnings[] = $indicatorStrategy === 'create'
                ? 'Indicator will be created before questions are imported.'
                : 'Questions will be imported without an indicator link.';
        }

        if ($payload['defaults']['type'] !== SurveyQuestion::TYPE_LIKERT) {
            $warnings[] = 'Only Likert bulk rows are auto-scored in this importer. Other types are imported as descriptive questions.';
        }

        return $warnings;
    }
}
