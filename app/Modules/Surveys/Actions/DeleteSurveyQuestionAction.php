<?php

namespace App\Modules\Surveys\Actions;

use App\Models\SurveyQuestion;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\SurveyQuestionSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeleteSurveyQuestionAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SurveyQuestionSafetyService $safetyService,
    ) {}

    public function handle(User $user, SurveyQuestion $question, ?Request $request = null): void
    {
        Gate::forUser($user)->authorize('update', $question->survey);
        $this->safetyService->ensureQuestionDeleteIsSafe($question);

        $survey = $question->survey;
        $questionId = $question->getKey();
        $questionKey = $question->question_key;
        $question->delete();

        $this->activityLogger->log('survey.question_deleted', $user, $survey->project, $survey, [
            'survey_id' => $survey->getKey(),
            'question_id' => $questionId,
            'question_key' => $questionKey,
        ], $request);
    }
}
