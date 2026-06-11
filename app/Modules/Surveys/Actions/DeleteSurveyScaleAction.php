<?php

namespace App\Modules\Surveys\Actions;

use App\Models\SurveyScale;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\SurveyScoringConfigSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeleteSurveyScaleAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SurveyScoringConfigSafetyService $safetyService,
    ) {}

    public function handle(User $user, SurveyScale $scale, ?Request $request = null): void
    {
        $scale->loadMissing('survey.project');
        Gate::forUser($user)->authorize('manageScoring', $scale->survey);
        $this->safetyService->ensureCanChangeScoring($scale->survey);

        $metadata = [
            'survey_id' => $scale->survey_id,
            'survey_scale_id' => $scale->getKey(),
            'name' => $scale->name,
        ];

        $scale->delete();

        $this->activityLogger->log('survey.scoring_scale_deleted', $user, $scale->survey->project, $scale, $metadata, $request);
    }
}
