<?php

namespace App\Modules\Surveys\Actions;

use App\Models\SurveyPage;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\SurveyQuestionSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeleteSurveyPageAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SurveyQuestionSafetyService $safetyService,
    ) {}

    public function handle(User $user, SurveyPage $page, ?Request $request = null): void
    {
        Gate::forUser($user)->authorize('update', $page->survey);
        $this->safetyService->ensurePageDeleteIsSafe($page);

        $survey = $page->survey;
        $pageId = $page->getKey();
        $page->delete();

        $this->activityLogger->log('survey.page_deleted', $user, $survey->project, $survey, [
            'survey_id' => $survey->getKey(),
            'page_id' => $pageId,
        ], $request);
    }
}
