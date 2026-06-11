<?php

namespace App\Modules\Surveys\Actions;

use App\Models\SurveyIndicator;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\SurveyScoringConfigSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeleteSurveyIndicatorAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SurveyScoringConfigSafetyService $safetyService,
    ) {}

    public function handle(User $user, SurveyIndicator $indicator, ?Request $request = null): void
    {
        $indicator->loadMissing('survey.project');
        Gate::forUser($user)->authorize('manageScoring', $indicator->survey);
        $this->safetyService->ensureCanChangeScoring($indicator->survey);

        $metadata = [
            'survey_id' => $indicator->survey_id,
            'survey_indicator_id' => $indicator->getKey(),
            'survey_scale_id' => $indicator->survey_scale_id,
            'name' => $indicator->name,
        ];

        $indicator->delete();

        $this->activityLogger->log('survey.scoring_indicator_deleted', $user, $indicator->survey->project, $indicator, $metadata, $request);
    }
}
