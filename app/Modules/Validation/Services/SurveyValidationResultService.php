<?php

namespace App\Modules\Validation\Services;

use App\Models\SurveyQuestion;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRevision;
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
        'content_relevance_score' => 'Content relevance',
        'language_clarity_score' => 'Language clarity',
        'construct_alignment_score' => 'Construct alignment',
        'measurability_score' => 'Measurability',
        'feasibility_score' => 'Feasibility of use',
        'ethical_suitability_score' => 'Ethical/privacy suitability',
    ];

    /**
     * @var array<string, string>
     */
    private const LEGACY_CRITERIA = [
        'content_relevance_score' => 'relevance_score',
        'language_clarity_score' => 'clarity_score',
        'construct_alignment_score' => 'appropriateness_score',
        'measurability_score' => 'clarity_score',
        'feasibility_score' => 'appropriateness_score',
        'ethical_suitability_score' => 'relevance_score',
    ];

    public function analyze(SurveyValidationRound $round): SurveyValidationResultData
    {
        $round->load([
            'survey.project',
            'survey.questions.scoring.indicator',
            'survey.validationRevisions.sourceAssignment.validator',
            'assignments.validator',
            'assignments.recommendation',
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
            aspectSummary: $this->aspectSummary($submittedAssignments),
            revisionMatrix: $this->revisionMatrix($round),
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
        $averageScores = [];

        foreach (array_keys(self::CRITERIA) as $criterion) {
            $aiken[$criterion] = $this->aikenV($scores, $criterion, $round->rating_scale_min, $round->rating_scale_max);
            $averageScores[$criterion] = $this->averageScore($scores, $criterion);
        }

        $aiken['relevance_score'] = $this->aikenV($scores, 'relevance_score', $round->rating_scale_min, $round->rating_scale_max);
        $aiken['clarity_score'] = $this->aikenV($scores, 'clarity_score', $round->rating_scale_min, $round->rating_scale_max);
        $aiken['language_score'] = $this->aikenV($scores, 'language_score', $round->rating_scale_min, $round->rating_scale_max);
        $aiken['appropriateness_score'] = $this->aikenV($scores, 'appropriateness_score', $round->rating_scale_min, $round->rating_scale_max);
        $averageAiken = $this->hasModernAspectScores($scores)
            ? $this->average(array_map(fn (string $criterion): ?float => $aiken[$criterion], array_keys(self::CRITERIA)))
            : $this->average([
                $aiken['relevance_score'],
                $aiken['clarity_score'],
                $aiken['language_score'],
                $aiken['appropriateness_score'],
            ]);

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
            'average_scores' => $averageScores,
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
            ->map(fn (SurveyValidationScore $score): mixed => $this->scoreForCriterion($score, $criterion))
            ->filter(fn (mixed $score): bool => is_numeric($score))
            ->map(fn (mixed $score): int => (int) $score);

        if ($ratings->isEmpty() || $scaleMax <= $scaleMin) {
            return null;
        }

        $sumS = $ratings->sum(fn (int $rating): int => max(0, $rating - $scaleMin));

        return round($sumS / ($ratings->count() * ($scaleMax - $scaleMin)), 4);
    }

    private function averageScore(Collection $scores, string $criterion): ?float
    {
        $ratings = $scores
            ->map(fn (SurveyValidationScore $score): mixed => $this->scoreForCriterion($score, $criterion))
            ->filter(fn (mixed $score): bool => is_numeric($score))
            ->map(fn (mixed $score): int => (int) $score);

        return $ratings->isEmpty() ? null : round($ratings->average(), 2);
    }

    private function scoreForCriterion(SurveyValidationScore $score, string $criterion): mixed
    {
        return $score->{$criterion} ?? $score->{self::LEGACY_CRITERIA[$criterion] ?? $criterion};
    }

    private function hasModernAspectScores(Collection $scores): bool
    {
        return $scores->contains(function (SurveyValidationScore $score): bool {
            foreach (array_keys(self::CRITERIA) as $criterion) {
                if ($score->{$criterion} !== null) {
                    return true;
                }
            }

            return false;
        });
    }

    private function itemCvi(Collection $scores, SurveyValidationRound $round): ?float
    {
        $ratings = $scores
            ->map(fn (SurveyValidationScore $score): mixed => $this->scoreForCriterion($score, 'content_relevance_score'))
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
        $scoreValues = collect($items)
            ->flatMap(fn (array $item): array => array_values($item['average_scores']))
            ->filter(fn (mixed $value): bool => $value !== null)
            ->values();
        $overallAverageScore = $scoreValues->isEmpty() ? null : round($scoreValues->average(), 2);
        $maxScore = max(1, $round->rating_scale_max);
        $percentageFeasibility = $overallAverageScore === null ? null : round(($overallAverageScore / $maxScore) * 100, 2);
        $decisionCounts = $submittedAssignments
            ->map(fn (SurveyValidationAssignment $assignment): ?string => $assignment->recommendation?->feasibility_decision)
            ->filter()
            ->countBy()
            ->all();

        return [
            'submitted_count' => $submittedAssignments->count(),
            'assigned_count' => $round->assignments->count(),
            'question_count' => $questions->count(),
            'overall_average_score' => $overallAverageScore,
            'percentage_feasibility' => $percentageFeasibility,
            'validation_category' => $this->validationCategory($overallAverageScore),
            'decision_counts' => $decisionCounts,
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
                'average_score' => $assignment->recommendation?->overall_score,
                'feasibility_decision' => $assignment->recommendation?->feasibility_decision,
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
     * @param  Collection<int, SurveyValidationAssignment>  $submittedAssignments
     * @return array<int, array<string, mixed>>
     */
    private function aspectSummary(Collection $submittedAssignments): array
    {
        $scores = $submittedAssignments
            ->flatMap(fn (SurveyValidationAssignment $assignment): Collection => $assignment->scores)
            ->values();

        return collect(self::CRITERIA)
            ->map(fn (string $label, string $criterion): array => [
                'aspect' => $criterion,
                'label' => $label,
                'average_score' => $this->averageScore($scores, $criterion),
                'aiken_v' => $this->aikenV($scores, $criterion, 1, 5),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function revisionMatrix(SurveyValidationRound $round): array
    {
        return $round->survey->validationRevisions
            ->map(fn (SurveyValidationRevision $revision): array => [
                'id' => $revision->getKey(),
                'validator_name' => $revision->sourceAssignment?->validator?->name ?? 'Validator',
                'validator_comment' => $revision->validator_comment,
                'revision_action' => $revision->revision_action,
                'status' => $revision->status,
                'researcher_note' => $revision->researcher_note,
            ])
            ->values()
            ->all();
    }

    private function validationCategory(?float $averageScore): string
    {
        if ($averageScore === null) {
            return 'No submitted validation yet';
        }

        return match (true) {
            $averageScore >= 4.20 => 'Very feasible / very valid',
            $averageScore >= 3.40 => 'Feasible with minor revision',
            $averageScore >= 2.60 => 'Fair, needs revision',
            $averageScore >= 1.80 => 'Less feasible',
            default => 'Not feasible',
        };
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
            'Berdasarkan hasil validasi ahli terhadap instrumen %s, diperoleh rata-rata skor %.2f dengan persentase kelayakan %s%% dan kategori %s. Nilai rata-rata Aiken\'s V sebesar %s dan S-CVI/Ave sebesar %s. Sebanyak %d butir dinyatakan layak, %d butir memerlukan revisi, dan %d butir tidak layak. Masukan validator digunakan sebagai dasar perbaikan instrumen sebelum pengambilan data.',
            $round->survey->title,
            (float) ($summary['overall_average_score'] ?? 0),
            $summary['percentage_feasibility'] === null ? '0.00' : number_format((float) $summary['percentage_feasibility'], 2),
            $summary['validation_category'],
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
