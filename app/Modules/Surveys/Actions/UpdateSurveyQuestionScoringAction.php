<?php

namespace App\Modules\Surveys\Actions;

use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionScoring;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\SurveyScoringConfigSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateSurveyQuestionScoringAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SurveyScoringConfigSafetyService $safetyService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, SurveyQuestion $question, array $attributes, ?Request $request = null): SurveyQuestionScoring
    {
        $question->loadMissing('survey.project');
        Gate::forUser($user)->authorize('manageScoring', $question->survey);
        $this->safetyService->ensureCanChangeScoring($question->survey);

        $isScored = (bool) ($attributes['is_scored'] ?? true);
        $this->safetyService->ensureQuestionCanBeScored($question, $isScored);
        $this->ensureIndicatorBelongsToSurvey($question, $attributes['survey_indicator_id'] ?? null);

        $scoring = SurveyQuestionScoring::updateOrCreate(
            ['survey_question_id' => $question->getKey()],
            [
                'survey_id' => $question->survey_id,
                'survey_indicator_id' => $attributes['survey_indicator_id'] ?? null,
                'is_scored' => $isScored,
                'score_min' => $attributes['score_min'] ?? null,
                'score_max' => $attributes['score_max'] ?? null,
                'weight' => (float) ($attributes['weight'] ?? 1),
                'is_reverse_scored' => (bool) ($attributes['is_reverse_scored'] ?? false),
                'settings' => $attributes['settings'] ?? null,
            ],
        );

        $this->activityLogger->log('survey.question_scoring_updated', $user, $question->survey->project, $scoring, [
            'survey_id' => $question->survey_id,
            'survey_question_id' => $question->getKey(),
            'survey_indicator_id' => $scoring->survey_indicator_id,
            'is_scored' => $scoring->is_scored,
            'is_reverse_scored' => $scoring->is_reverse_scored,
            'weight' => $scoring->weight,
        ], $request);

        return $scoring->fresh(['question', 'indicator']);
    }

    private function ensureIndicatorBelongsToSurvey(SurveyQuestion $question, ?string $indicatorId): void
    {
        if ($indicatorId !== null && $question->survey->indicators()->whereKey($indicatorId)->doesntExist()) {
            throw ValidationException::withMessages([
                'survey_indicator_id' => 'Selected indicator does not belong to this survey.',
            ]);
        }
    }
}
