<?php

namespace App\Modules\Analysis\Services;

use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class QuestionDescriptiveAnalyzer
{
    /**
     * @param  Collection<int, SurveyAnswer>  $answers
     * @return array<string, mixed>
     */
    public function analyze(SurveyQuestion $question, Collection $answers, int $submittedResponseCount): array
    {
        $values = $answers
            ->map(fn (SurveyAnswer $answer): mixed => $answer->answer_value)
            ->filter(fn (mixed $value): bool => ! $this->isMissing($value))
            ->values();

        $base = [
            'question_id' => $question->getKey(),
            'question_key' => $question->question_key,
            'label' => $question->label,
            'type' => $question->type,
            'answered_count' => $values->count(),
            'missing_count' => max(0, $submittedResponseCount - $values->count()),
        ];

        return match ($question->type) {
            SurveyQuestion::TYPE_SHORT_TEXT,
            SurveyQuestion::TYPE_LONG_TEXT => [
                ...$base,
                'sample_answers' => $this->sampleAnswers($values),
            ],
            SurveyQuestion::TYPE_SINGLE_CHOICE => [
                ...$base,
                'frequencies' => $this->frequencies($values, $this->options($question), $submittedResponseCount),
            ],
            SurveyQuestion::TYPE_MULTIPLE_CHOICE => [
                ...$base,
                'frequencies' => $this->multipleChoiceFrequencies($values, $this->options($question), $submittedResponseCount),
            ],
            SurveyQuestion::TYPE_LIKERT => [
                ...$base,
                'frequencies' => $this->frequencies($values, $this->scale($question), $submittedResponseCount),
                ...$this->numericStats($values),
            ],
            SurveyQuestion::TYPE_LIKERT_MATRIX => [
                ...$base,
                'matrix_summary' => $this->matrixSummary($values),
                'advanced_flattening_deferred' => true,
            ],
            SurveyQuestion::TYPE_NUMBER => [
                ...$base,
                ...$this->numericStats($values),
            ],
            SurveyQuestion::TYPE_DATE => [
                ...$base,
                ...$this->dateStats($values),
            ],
            SurveyQuestion::TYPE_CONSENT => [
                ...$base,
                ...$this->consentStats($values),
            ],
            default => $base,
        };
    }

    private function isMissing(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return is_array($value) && count($value) === 0;
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return array<int, string>
     */
    private function sampleAnswers(Collection $values): array
    {
        $limit = (int) config('researchhub_analysis.sample_answer_limit', 5);
        $maxLength = (int) config('researchhub_analysis.sample_answer_max_length', 140);

        return $values
            ->map(fn (mixed $value): string => Str::limit(trim(strip_tags((string) $value)), $maxLength))
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function options(SurveyQuestion $question): array
    {
        $options = $question->options ?? [];
        $choices = $options['choices'] ?? $options['options'] ?? [];

        return collect($choices)
            ->map(fn (mixed $choice): string => (string) $choice)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function scale(SurveyQuestion $question): array
    {
        $settings = $question->settings ?? [];
        $options = $question->options ?? [];
        $scale = $settings['scale'] ?? $options['scale'] ?? config('researchhub_surveys.default_likert_scale', [1, 2, 3, 4, 5]);

        return collect($scale)
            ->map(fn (mixed $value): string => (string) $value)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @param  array<int, string>  $knownOptions
     * @return array<int, array{value: string, count: int, percentage: float}>
     */
    private function frequencies(Collection $values, array $knownOptions, int $respondentCount): array
    {
        $counts = $values
            ->map(fn (mixed $value): string => (string) $value)
            ->countBy();

        $keys = collect($knownOptions)
            ->merge($counts->keys())
            ->unique()
            ->values();

        return $keys
            ->map(fn (string $key): array => [
                'value' => $key,
                'count' => (int) ($counts[$key] ?? 0),
                'percentage' => $this->percentage((int) ($counts[$key] ?? 0), $respondentCount),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @param  array<int, string>  $knownOptions
     * @return array<int, array{value: string, count: int, percentage: float}>
     */
    private function multipleChoiceFrequencies(Collection $values, array $knownOptions, int $respondentCount): array
    {
        $flat = $values
            ->flatMap(fn (mixed $value): array => is_array($value) ? $value : [$value])
            ->map(fn (mixed $value): string => (string) $value)
            ->filter();

        $counts = $flat->countBy();
        $keys = collect($knownOptions)
            ->merge($counts->keys())
            ->unique()
            ->values();

        return $keys
            ->map(fn (string $key): array => [
                'value' => $key,
                'count' => (int) ($counts[$key] ?? 0),
                'percentage' => $this->percentage((int) ($counts[$key] ?? 0), $respondentCount),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return array{count: int, mean: float|null, median: float|null, min: float|null, max: float|null, standard_deviation: float|null}
     */
    private function numericStats(Collection $values): array
    {
        $numbers = $values
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value)
            ->sort()
            ->values();

        if ($numbers->isEmpty()) {
            return [
                'count' => 0,
                'mean' => null,
                'median' => null,
                'min' => null,
                'max' => null,
                'standard_deviation' => null,
            ];
        }

        $mean = $numbers->avg();

        return [
            'count' => $numbers->count(),
            'mean' => $this->round($mean),
            'median' => $this->round($this->median($numbers)),
            'min' => $this->round($numbers->first()),
            'max' => $this->round($numbers->last()),
            'standard_deviation' => $this->round($this->standardDeviation($numbers, $mean)),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return array{min_date: string|null, max_date: string|null}
     */
    private function dateStats(Collection $values): array
    {
        $dates = $values
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->sort()
            ->values();

        return [
            'min_date' => $dates->first(),
            'max_date' => $dates->last(),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return array{accepted_count: int, not_accepted_count: int}
     */
    private function consentStats(Collection $values): array
    {
        $accepted = $values->filter(fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN))->count();

        return [
            'accepted_count' => $accepted,
            'not_accepted_count' => max(0, $values->count() - $accepted),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return array<string, mixed>
     */
    private function matrixSummary(Collection $values): array
    {
        $summary = [];

        foreach ($values as $value) {
            if (! is_array($value)) {
                continue;
            }

            foreach ($value as $row => $column) {
                $rowKey = (string) $row;
                $columnKey = is_scalar($column) ? (string) $column : json_encode($column, JSON_UNESCAPED_SLASHES);
                $summary[$rowKey][$columnKey] = ($summary[$rowKey][$columnKey] ?? 0) + 1;
            }
        }

        return $summary;
    }

    /**
     * @param  Collection<int, float>  $numbers
     */
    private function median(Collection $numbers): float
    {
        $count = $numbers->count();
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $numbers[$middle];
        }

        return ((float) $numbers[$middle - 1] + (float) $numbers[$middle]) / 2;
    }

    /**
     * @param  Collection<int, float>  $numbers
     */
    private function standardDeviation(Collection $numbers, float $mean): float
    {
        $variance = $numbers
            ->map(fn (float $value): float => ($value - $mean) ** 2)
            ->avg();

        return sqrt($variance);
    }

    private function percentage(int $count, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return $this->round(($count / $total) * 100, 2);
    }

    private function round(float|int|null $value, int $precision = 4): ?float
    {
        return $value === null ? null : round((float) $value, $precision);
    }
}
