<?php

namespace App\Modules\Validation\Services;

use App\Models\SurveyQuestion;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\SurveyValidationScore;
use App\Modules\Validation\DTOs\SurveyValidationResultData;
use Illuminate\Support\Collection;

class SurveyValidationResultService
{
    public const STATUS_VALID = 'valid';

    public const STATUS_REVISE = 'revise';

    public const STATUS_REJECT = 'reject';

    public const STATUS_NO_DATA = 'no_data';

    public const VALID_THRESHOLD = 0.80;

    public const REVISE_THRESHOLD = 0.60;

    /**
     * @var array<string, string>
     */
    private const CRITERIA = [
        'relevance_score' => 'Relevance',
        'clarity_score' => 'Clarity',
        'language_score' => 'Language',
        'appropriateness_score' => 'Appropriateness',
    ];

    public function analyze(SurveyValidationRound $round): SurveyValidationResultData
    {
        $round->load([
            'survey.project',
            'survey.questions.scoring.indicator',
            'assignments.validator',
            'assignments.scores.question',
            'creator',
        ]);

        $questions = $round->survey->questions
            ->reject(fn (SurveyQuestion $question): bool => $question->type === SurveyQuestion::TYPE_HIDDEN)
            ->sortBy('sort_order')
            ->values();

        $submittedAssignments = $round->assignments
            ->filter(fn (SurveyValidationAssignment $assignment): bool => $assignment->isSubmitted())
            ->values();

        $items = $questions
            ->map(fn (SurveyQuestion $question, int $index): array => $this->itemResult($round, $question, $index + 1, $submittedAssignments))
            ->values()
            ->all();

        $summary = $this->summary($round, $questions, $items, $submittedAssignments);
        $comments = $this->comments($questions, $submittedAssignments);

        return new SurveyValidationResultData(
            round: $round,
            summary: $summary,
            validators: $this->validators($round),
            items: $items,
            comments: $comments,
            narrative: $this->narrative($round, $summary),
            cvrNote: 'CVR requires an explicit essential/not-essential expert judgment and is not calculated for this round.',
        );
    }

    /**
     * @param  Collection<int, SurveyValidationAssignment>  $submittedAssignments
     * @return array<string, mixed>
     */
    private function itemResult(SurveyValidationRound $round, SurveyQuestion $question, int $order, Collection $submittedAssignments): array
    {
        $scores = $submittedAssignments
            ->flatMap(fn (SurveyValidationAssignment $assignment): Collection => $assignment->scores)
            ->filter(fn (SurveyValidationScore $score): bool => $score->survey_question_id === $question->getKey())
            ->values();

        $aiken = [];

        foreach (array_keys(self::CRITERIA) as $criterion) {
            $aiken[$criterion] = $this->aikenV($scores, $criterion, $round->rating_scale_min, $round->rating_scale_max);
        }

        $averageAiken = $this->average(array_values($aiken));
        $iCvi = $this->itemCvi($scores, $round);

        return [
            'order' => $order,
            'question_id' => $question->getKey(),
            'question' => $question,
            'question_text' => $question->label,
            'question_type' => $question->type,
            'indicator' => $question->scoring?->indicator?->name,
            'submitted_score_count' => $scores->count(),
            'aiken' => $aiken,
            'average_aiken_v' => $averageAiken,
            'i_cvi' => $iCvi,
            's_cvi_ua_item' => $iCvi !== null && $iCvi >= 1.0,
            'status' => $this->itemStatus($averageAiken, $iCvi),
            'recommendations' => $scores
                ->pluck('recommendation')
                ->filter()
                ->countBy()
                ->all(),
        ];
    }

    private function aikenV(Collection $scores, string $criterion, int $scaleMin, int $scaleMax): ?float
    {
        $ratings = $scores
            ->pluck($criterion)
            ->filter(fn (mixed $score): bool => is_numeric($score))
            ->map(fn (mixed $score): int => (int) $score);

        if ($ratings->isEmpty() || $scaleMax <= $scaleMin) {
            return null;
        }

        $sumS = $ratings->sum(fn (int $rating): int => max(0, $rating - $scaleMin));

        return round($sumS / ($ratings->count() * ($scaleMax - $scaleMin)), 4);
    }

    private function itemCvi(Collection $scores, SurveyValidationRound $round): ?float
    {
        $ratings = $scores
            ->pluck('relevance_score')
            ->filter(fn (mixed $score): bool => is_numeric($score))
            ->map(fn (mixed $score): int => (int) $score);

        if ($ratings->isEmpty()) {
            return null;
        }

        $relevantThreshold = $this->relevantThreshold($round);
        $relevantCount = $ratings->filter(fn (int $score): bool => $score >= $relevantThreshold)->count();

        return round($relevantCount / $ratings->count(), 4);
    }

    private function relevantThreshold(SurveyValidationRound $round): int
    {
        if ($round->rating_scale_min === 1 && $round->rating_scale_max === 4) {
            return 3;
        }

        return (int) ceil(($round->rating_scale_min + $round->rating_scale_max) / 2);
    }

    private function itemStatus(?float $averageAiken, ?float $iCvi): string
    {
        if ($averageAiken === null || $iCvi === null) {
            return self::STATUS_NO_DATA;
        }

        if ($averageAiken >= self::VALID_THRESHOLD && $iCvi >= self::VALID_THRESHOLD) {
            return self::STATUS_VALID;
        }

        if ($averageAiken < self::REVISE_THRESHOLD || $iCvi < self::REVISE_THRESHOLD) {
            return self::STATUS_REJECT;
        }

        return self::STATUS_REVISE;
    }

    /**
     * @param  Collection<int, SurveyQuestion>  $questions
     * @param  array<int, array<string, mixed>>  $items
     * @param  Collection<int, SurveyValidationAssignment>  $submittedAssignments
     * @return array<string, mixed>
     */
    private function summary(SurveyValidationRound $round, Collection $questions, array $items, Collection $submittedAssignments): array
    {
        $aikenValues = collect($items)->pluck('average_aiken_v')->filter(fn (mixed $value): bool => $value !== null)->all();
        $iCviValues = collect($items)->pluck('i_cvi')->filter(fn (mixed $value): bool => $value !== null)->all();

        return [
            'submitted_count' => $submittedAssignments->count(),
            'assigned_count' => $round->assignments->count(),
            'question_count' => $questions->count(),
            'average_aiken_v' => $this->average($aikenValues),
            'average_i_cvi' => $this->average($iCviValues),
            's_cvi_ave' => $this->average($iCviValues),
            's_cvi_ua' => $questions->isEmpty() ? null : round(collect($items)->where('s_cvi_ua_item', true)->count() / $questions->count(), 4),
            'valid_count' => collect($items)->where('status', self::STATUS_VALID)->count(),
            'revise_count' => collect($items)->where('status', self::STATUS_REVISE)->count(),
            'reject_count' => collect($items)->where('status', self::STATUS_REJECT)->count(),
            'no_data_count' => collect($items)->where('status', self::STATUS_NO_DATA)->count(),
            'is_preliminary' => $submittedAssignments->count() > 0 && $submittedAssignments->count() < $round->assignments->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validators(SurveyValidationRound $round): array
    {
        return $round->assignments
            ->map(fn (SurveyValidationAssignment $assignment): array => [
                'validator_name' => $assignment->validator?->name ?? 'Missing validator',
                'role' => $assignment->role,
                'status' => $assignment->status,
                'opened_at' => $assignment->opened_at,
                'submitted_at' => $assignment->submitted_at,
                'expires_at' => $assignment->expires_at,
                'revoked_at' => $assignment->revoked_at,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SurveyQuestion>  $questions
     * @param  Collection<int, SurveyValidationAssignment>  $submittedAssignments
     * @return array<int, array<string, mixed>>
     */
    private function comments(Collection $questions, Collection $submittedAssignments): array
    {
        return $questions
            ->map(function (SurveyQuestion $question, int $index) use ($submittedAssignments): array {
                $comments = $submittedAssignments
                    ->flatMap(function (SurveyValidationAssignment $assignment) use ($question): Collection {
                        return $assignment->scores
                            ->filter(fn (SurveyValidationScore $score): bool => $score->survey_question_id === $question->getKey())
                            ->filter(fn (SurveyValidationScore $score): bool => filled($score->comment) || filled($score->recommendation))
                            ->map(fn (SurveyValidationScore $score): array => [
                                'validator_name' => $assignment->validator?->name ?? 'Missing validator',
                                'role' => $assignment->role,
                                'comment' => $score->comment,
                                'recommendation' => $score->recommendation,
                            ]);
                    })
                    ->values()
                    ->all();

                return [
                    'order' => $index + 1,
                    'question_id' => $question->getKey(),
                    'question_text' => $question->label,
                    'comments' => $comments,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, float|null>  $values
     */
    private function average(array $values): ?float
    {
        $filtered = collect($values)->filter(fn (mixed $value): bool => $value !== null);

        if ($filtered->isEmpty()) {
            return null;
        }

        return round($filtered->average(), 4);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function narrative(SurveyValidationRound $round, array $summary): string
    {
        if ((int) $summary['submitted_count'] === 0) {
            return 'Belum terdapat penilaian validasi ahli yang dapat diringkas untuk instrumen '.$round->survey->title.'.';
        }

        return sprintf(
            'Berdasarkan hasil validasi ahli terhadap instrumen %s, diperoleh nilai rata-rata Aiken\'s V sebesar %s dan S-CVI/Ave sebesar %s. Sebanyak %d butir dinyatakan layak, %d butir memerlukan revisi, dan %d butir tidak layak. Masukan validator digunakan sebagai dasar perbaikan instrumen sebelum pengambilan data.',
            $round->survey->title,
            $this->formatMetric($summary['average_aiken_v']),
            $this->formatMetric($summary['s_cvi_ave']),
            $summary['valid_count'],
            $summary['revise_count'],
            $summary['reject_count'],
        );
    }

    private function formatMetric(?float $metric): string
    {
        return $metric === null ? 'belum tersedia' : number_format($metric, 3);
    }
}
