<?php

namespace App\Modules\Validation\Actions;

use App\Models\SurveyQuestion;
use App\Models\SurveyValidationAssignment;
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
    public function handle(SurveyValidationAssignment $assignment, array $scores, ?Request $request = null): SurveyValidationAssignment
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

        foreach ($questions as $question) {
            $questionScores = $scores[$question->getKey()] ?? [];
            $this->assertScoreSet($assignment, $question->getKey(), $questionScores);

            SurveyValidationScore::updateOrCreate([
                'survey_validation_assignment_id' => $assignment->getKey(),
                'survey_question_id' => $question->getKey(),
            ], [
                'relevance_score' => (int) $questionScores['relevance_score'],
                'clarity_score' => (int) $questionScores['clarity_score'],
                'language_score' => (int) $questionScores['language_score'],
                'appropriateness_score' => (int) $questionScores['appropriateness_score'],
                'comment' => $questionScores['comment'] ?? null,
                'recommendation' => (string) $questionScores['recommendation'],
            ]);
        }

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
    private function assertScoreSet(SurveyValidationAssignment $assignment, string $questionId, mixed $questionScores): void
    {
        if (! is_array($questionScores)) {
            throw ValidationException::withMessages([
                "scores.{$questionId}" => 'Score every validation criterion for this question.',
            ]);
        }

        foreach (['relevance_score', 'clarity_score', 'language_score', 'appropriateness_score'] as $field) {
            $score = $questionScores[$field] ?? null;

            if (! is_numeric($score)
                || (int) $score < $assignment->round->rating_scale_min
                || (int) $score > $assignment->round->rating_scale_max) {
                throw ValidationException::withMessages([
                    "scores.{$questionId}.{$field}" => 'Each score must be within the validation round rating scale.',
                ]);
            }
        }

        if (! in_array($questionScores['recommendation'] ?? null, SurveyValidationScore::RECOMMENDATIONS, true)) {
            throw ValidationException::withMessages([
                "scores.{$questionId}.recommendation" => 'Select a recommendation for every question.',
            ]);
        }
    }
}
