<?php

namespace App\Modules\Validation\Actions;

use App\Models\SurveyQuestion;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRecommendation;
use App\Models\SurveyValidationRevision;
use App\Models\SurveyValidationScore;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubmitSurveyValidationScoresAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $scores
     */
    public function handle(SurveyValidationAssignment $assignment, array $scores, ?Request $request = null, array $recommendationData = []): SurveyValidationAssignment
    {
        $assignment->loadMissing('round.survey.questions', 'validator');

        if (! $assignment->isAccessible()) {
            throw ValidationException::withMessages([
                'validation' => 'This validation link is no longer available.',
            ]);
        }

        $questions = $assignment->round->survey->questions()
            ->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)
            ->orderBy('sort_order')
            ->get();

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'validation' => 'This survey has no questions to validate.',
            ]);
        }

        foreach ($questions as $index => $question) {
            $questionScores = $scores[$question->getKey()] ?? [];
            $this->assertScoreSet($assignment, $question->getKey(), $questionScores, $index + 1);

            SurveyValidationScore::updateOrCreate([
                'survey_validation_assignment_id' => $assignment->getKey(),
                'survey_question_id' => $question->getKey(),
            ], [
                'content_relevance_score' => (int) $this->scoreValue($questionScores, 'content_relevance_score', 'relevance_score'),
                'language_clarity_score' => (int) $this->scoreValue($questionScores, 'language_clarity_score', 'clarity_score'),
                'construct_alignment_score' => (int) $this->scoreValue($questionScores, 'construct_alignment_score', 'appropriateness_score'),
                'measurability_score' => (int) $this->scoreValue($questionScores, 'measurability_score', 'clarity_score'),
                'feasibility_score' => (int) $this->scoreValue($questionScores, 'feasibility_score', 'appropriateness_score'),
                'ethical_suitability_score' => (int) $this->scoreValue($questionScores, 'ethical_suitability_score', 'relevance_score'),
                'relevance_score' => (int) $this->scoreValue($questionScores, 'relevance_score', 'content_relevance_score'),
                'clarity_score' => (int) $this->scoreValue($questionScores, 'clarity_score', 'language_clarity_score'),
                'language_score' => (int) $this->scoreValue($questionScores, 'language_score', 'language_clarity_score'),
                'appropriateness_score' => (int) $this->scoreValue($questionScores, 'appropriateness_score', 'construct_alignment_score'),
                'comment' => $questionScores['comment'] ?? null,
                'recommendation' => (string) $questionScores['recommendation'],
            ]);
        }

        $recommendation = $this->storeRecommendation($assignment, $scores, $recommendationData);
        $this->storeRevisionMatrix($assignment, $scores, $recommendation);
        $assignment->markSubmitted();

        $this->activityLogger->log('survey_validation_assignment.submitted', null, $assignment->round->project, $assignment, [
            'survey_validation_round_id' => $assignment->survey_validation_round_id,
            'survey_validation_assignment_id' => $assignment->getKey(),
            'survey_id' => $assignment->round->survey_id,
            'expert_validator_id' => $assignment->expert_validator_id,
            'research_project_id' => $assignment->round->research_project_id,
            'status' => $assignment->status,
        ], $request);

        return $assignment;
    }

    /**
     * @param  array<string, mixed>  $questionScores
     */
    private function assertScoreSet(SurveyValidationAssignment $assignment, string $questionId, mixed $questionScores, int $questionNumber): void
    {
        if (! is_array($questionScores)) {
            throw ValidationException::withMessages([
                "scores.{$questionId}" => "Butir {$questionNumber}: lengkapi seluruh skor penilaian.",
            ]);
        }

        foreach ([
            'content_relevance_score' => 'relevansi konten',
            'language_clarity_score' => 'kejelasan bahasa',
            'construct_alignment_score' => 'keselarasan konstruk',
            'measurability_score' => 'keterukuran',
            'feasibility_score' => 'kelayakan penggunaan',
            'ethical_suitability_score' => 'kesesuaian etik/privasi',
        ] as $field => $label) {
            $score = $this->scoreValue($questionScores, $field, $this->legacyFieldFor($field));

            if (! is_numeric($score)
                || (int) $score < $assignment->round->rating_scale_min
                || (int) $score > $assignment->round->rating_scale_max) {
                throw ValidationException::withMessages([
                    "scores.{$questionId}.{$field}" => "Butir {$questionNumber}: pilih skor {$label} dalam skala {$assignment->round->rating_scale_min}-{$assignment->round->rating_scale_max}.",
                ]);
            }
        }

        if (! in_array($questionScores['recommendation'] ?? null, SurveyValidationScore::RECOMMENDATIONS, true)) {
            throw ValidationException::withMessages([
                "scores.{$questionId}.recommendation" => "Butir {$questionNumber}: pilih rekomendasi validasi.",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $questionScores
     */
    private function scoreValue(array $questionScores, string $field, ?string $fallback = null): mixed
    {
        return $questionScores[$field] ?? ($fallback ? ($questionScores[$fallback] ?? null) : null);
    }

    private function legacyFieldFor(string $field): ?string
    {
        return match ($field) {
            'content_relevance_score' => 'relevance_score',
            'language_clarity_score' => 'clarity_score',
            'construct_alignment_score' => 'appropriateness_score',
            'measurability_score' => 'clarity_score',
            'feasibility_score' => 'appropriateness_score',
            'ethical_suitability_score' => 'relevance_score',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $scores
     * @param  array<string, mixed>  $recommendationData
     */
    private function storeRecommendation(SurveyValidationAssignment $assignment, array $scores, array $recommendationData): SurveyValidationRecommendation
    {
        $decision = $recommendationData['feasibility_decision'] ?? SurveyValidationRecommendation::DECISION_VALID_WITH_MINOR_REVISION;

        if (! in_array($decision, SurveyValidationRecommendation::DECISIONS, true)) {
            throw ValidationException::withMessages([
                'feasibility_decision' => 'Pilih keputusan kelayakan akhir yang valid.',
            ]);
        }

        return SurveyValidationRecommendation::updateOrCreate([
            'survey_validation_assignment_id' => $assignment->getKey(),
        ], [
            'survey_id' => $assignment->round->survey_id,
            'overall_score' => $this->averageOverallScore($scores),
            'feasibility_decision' => $decision,
            'general_comments' => $recommendationData['general_comments'] ?? null,
            'revision_suggestions' => $recommendationData['revision_suggestions'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $scores
     */
    private function averageOverallScore(array $scores): ?float
    {
        $values = collect($scores)
            ->filter(fn (mixed $scoreSet): bool => is_array($scoreSet))
            ->flatMap(fn (array $scoreSet): array => [
                $this->scoreValue($scoreSet, 'content_relevance_score', 'relevance_score'),
                $this->scoreValue($scoreSet, 'language_clarity_score', 'clarity_score'),
                $this->scoreValue($scoreSet, 'construct_alignment_score', 'appropriateness_score'),
                $this->scoreValue($scoreSet, 'measurability_score', 'clarity_score'),
                $this->scoreValue($scoreSet, 'feasibility_score', 'appropriateness_score'),
                $this->scoreValue($scoreSet, 'ethical_suitability_score', 'relevance_score'),
            ])
            ->filter(fn (mixed $score): bool => is_numeric($score))
            ->map(fn (mixed $score): int => (int) $score);

        return $values->isEmpty() ? null : round($values->average(), 2);
    }

    /**
     * @param  array<string, mixed>  $scores
     */
    private function storeRevisionMatrix(SurveyValidationAssignment $assignment, array $scores, SurveyValidationRecommendation $recommendation): void
    {
        foreach ($scores as $scoreSet) {
            if (! is_array($scoreSet) || blank($scoreSet['comment'] ?? null)) {
                continue;
            }

            SurveyValidationRevision::create([
                'survey_id' => $assignment->round->survey_id,
                'source_assignment_id' => $assignment->getKey(),
                'validator_comment' => (string) $scoreSet['comment'],
                'revision_action' => null,
                'status' => SurveyValidationRevision::STATUS_PENDING,
                'researcher_note' => null,
            ]);
        }

        if (filled($recommendation->revision_suggestions)) {
            SurveyValidationRevision::create([
                'survey_id' => $assignment->round->survey_id,
                'source_assignment_id' => $assignment->getKey(),
                'validator_comment' => (string) $recommendation->revision_suggestions,
                'revision_action' => null,
                'status' => SurveyValidationRevision::STATUS_PENDING,
                'researcher_note' => null,
            ]);
        }
    }
}
