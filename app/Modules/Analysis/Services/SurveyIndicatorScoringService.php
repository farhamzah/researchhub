<?php

namespace App\Modules\Analysis\Services;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyIndicator;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionScoring;
use App\Models\SurveyResponse;
use Illuminate\Support\Collection;

class SurveyIndicatorScoringService
{
    private const SUPPORTED_TYPES = [
        SurveyQuestion::TYPE_LIKERT,
        SurveyQuestion::TYPE_SINGLE_CHOICE,
        SurveyQuestion::TYPE_NUMBER,
        SurveyQuestion::TYPE_CONSENT,
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function summarize(Survey $survey): array
    {
        $survey->loadMissing(['indicators.scale', 'questionScorings.question']);
        $submittedResponses = $survey->responses()
            ->submitted()
            ->official()
            ->with('answers')
            ->get();

        return $survey->indicators
            ->map(fn (SurveyIndicator $indicator): array => $this->summarizeIndicator($indicator, $submittedResponses))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SurveyResponse>  $submittedResponses
     * @return array<string, mixed>
     */
    private function summarizeIndicator(SurveyIndicator $indicator, Collection $submittedResponses): array
    {
        $scorings = $indicator->questionScorings
            ->filter(fn (SurveyQuestionScoring $scoring): bool => $this->canScore($scoring))
            ->values();

        $respondentScores = $submittedResponses
            ->map(fn (SurveyResponse $response): ?array => $this->responseScore($response, $scorings))
            ->filter()
            ->values();
        $scores = $respondentScores->pluck('score')->values();
        $mean = $this->round($scores->avg());

        return [
            'indicator_id' => $indicator->getKey(),
            'indicator_name' => $indicator->name,
            'scale_id' => $indicator->survey_scale_id,
            'scale_name' => $indicator->scale?->name,
            'item_count' => $scorings->count(),
            'respondent_count' => $respondentScores->count(),
            'mean' => $mean,
            'median' => $this->median($scores),
            'min' => $this->round($scores->min()),
            'max' => $this->round($scores->max()),
            'standard_deviation' => $this->standardDeviation($scores),
            'missing_count' => max(0, $submittedResponses->count() - $respondentScores->count()),
            'interpretation_label' => $this->interpretationLabel($indicator, $mean),
            'respondent_scores' => $respondentScores->all(),
        ];
    }

    /**
     * @param  Collection<int, SurveyQuestionScoring>  $scorings
     * @return array<string, mixed>|null
     */
    public function responseScore(SurveyResponse $response, Collection $scorings): ?array
    {
        $answers = $response->answers->keyBy('survey_question_id');
        $weightedScore = 0.0;
        $weightTotal = 0.0;
        $answeredItemCount = 0;

        foreach ($scorings as $scoring) {
            $answer = $answers->get($scoring->survey_question_id);
            $score = $answer instanceof SurveyAnswer ? $this->answerScore($scoring, $answer->answer_value) : null;

            if ($score === null) {
                continue;
            }

            $weight = max(0.0001, (float) $scoring->weight);
            $weightedScore += $score * $weight;
            $weightTotal += $weight;
            $answeredItemCount++;
        }

        if ($weightTotal <= 0 || $answeredItemCount === 0) {
            return null;
        }

        return [
            'survey_response_id' => $response->getKey(),
            'score' => $this->round($weightedScore / $weightTotal),
            'answered_item_count' => $answeredItemCount,
        ];
    }

    private function canScore(SurveyQuestionScoring $scoring): bool
    {
        return $scoring->is_scored
            && $scoring->question !== null
            && in_array($scoring->question->type, self::SUPPORTED_TYPES, true)
            && $scoring->question->type !== SurveyQuestion::TYPE_HIDDEN;
    }

    private function answerScore(SurveyQuestionScoring $scoring, mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $score = match ($scoring->question->type) {
            SurveyQuestion::TYPE_LIKERT,
            SurveyQuestion::TYPE_NUMBER => is_numeric($value) ? (float) $value : null,
            SurveyQuestion::TYPE_SINGLE_CHOICE => $this->choiceScore($scoring, $value),
            SurveyQuestion::TYPE_CONSENT => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null
                ? null
                : (filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1.0 : 0.0),
            default => null,
        };

        if ($score === null) {
            return null;
        }

        $score = $this->clamp($score, $scoring);

        if ($scoring->is_reverse_scored) {
            $score = $this->reverseScore($score, $scoring);
        }

        return $this->round($score);
    }

    private function choiceScore(SurveyQuestionScoring $scoring, mixed $value): ?float
    {
        $key = (string) $value;
        $settings = $scoring->settings ?? [];
        $questionSettings = $scoring->question->settings ?? [];
        $questionOptions = $scoring->question->options ?? [];

        $scores = $settings['scores'] ?? $questionSettings['scores'] ?? $questionOptions['scores'] ?? [];
        if (isset($scores[$key]) && is_numeric($scores[$key])) {
            return (float) $scores[$key];
        }

        foreach (($questionOptions['choices'] ?? []) as $choice) {
            if (is_array($choice) && (string) ($choice['value'] ?? $choice['label'] ?? '') === $key && isset($choice['score']) && is_numeric($choice['score'])) {
                return (float) $choice['score'];
            }
        }

        return null;
    }

    private function clamp(float $score, SurveyQuestionScoring $scoring): float
    {
        if ($scoring->score_min !== null) {
            $score = max($score, (float) $scoring->score_min);
        }

        if ($scoring->score_max !== null) {
            $score = min($score, (float) $scoring->score_max);
        }

        return $score;
    }

    private function reverseScore(float $score, SurveyQuestionScoring $scoring): float
    {
        $min = $this->scoreMin($scoring);
        $max = $this->scoreMax($scoring);

        if ($min === null || $max === null) {
            return $score;
        }

        return $max + $min - $score;
    }

    private function scoreMin(SurveyQuestionScoring $scoring): ?float
    {
        if ($scoring->score_min !== null) {
            return (float) $scoring->score_min;
        }

        $scale = $scoring->question->settings['scale'] ?? $scoring->question->options['scale'] ?? null;
        if (is_array($scale) && $scale !== []) {
            return (float) min($scale);
        }

        return null;
    }

    private function scoreMax(SurveyQuestionScoring $scoring): ?float
    {
        if ($scoring->score_max !== null) {
            return (float) $scoring->score_max;
        }

        $scale = $scoring->question->settings['scale'] ?? $scoring->question->options['scale'] ?? null;
        if (is_array($scale) && $scale !== []) {
            return (float) max($scale);
        }

        return null;
    }

    private function interpretationLabel(SurveyIndicator $indicator, ?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        foreach (($indicator->interpretation_rules ?? []) as $rule) {
            if (! is_array($rule) || ! isset($rule['min'], $rule['max'], $rule['label'])) {
                continue;
            }

            if ($score >= (float) $rule['min'] && $score <= (float) $rule['max']) {
                return (string) $rule['label'];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, mixed>  $scores
     */
    private function median(Collection $scores): ?float
    {
        $numbers = $scores->filter(fn (mixed $value): bool => is_numeric($value))->map(fn (mixed $value): float => (float) $value)->sort()->values();
        $count = $numbers->count();

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $this->round($numbers[$middle]);
        }

        return $this->round(($numbers[$middle - 1] + $numbers[$middle]) / 2);
    }

    /**
     * @param  Collection<int, mixed>  $scores
     */
    private function standardDeviation(Collection $scores): ?float
    {
        $numbers = $scores->filter(fn (mixed $value): bool => is_numeric($value))->map(fn (mixed $value): float => (float) $value)->values();

        if ($numbers->isEmpty()) {
            return null;
        }

        $mean = $numbers->avg();
        $variance = $numbers->map(fn (float $value): float => ($value - $mean) ** 2)->avg();

        return $this->round(sqrt($variance));
    }

    private function round(float|int|null $value): ?float
    {
        return $value === null ? null : round((float) $value, 4);
    }
}
