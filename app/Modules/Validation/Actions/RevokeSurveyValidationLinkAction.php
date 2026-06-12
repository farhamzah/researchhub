<?php

namespace App\Modules\Validation\Actions;

use App\Models\Survey;
use App\Models\SurveyValidationAssignment;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RevokeSurveyValidationLinkAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Survey $survey, SurveyValidationAssignment $assignment, ?Request $request = null): SurveyValidationAssignment
    {
        $assignment->loadMissing('round');
        abort_unless($assignment->round?->survey_id === $survey->getKey(), 404);
        Gate::forUser($user)->authorize('manageValidation', $survey);

        $assignment->markRevoked();

        $this->activityLogger->log('survey_validation_link.revoked', $user, $survey->project, $assignment, [
            'survey_validation_round_id' => $assignment->survey_validation_round_id,
            'survey_validation_assignment_id' => $assignment->getKey(),
            'survey_id' => $survey->getKey(),
            'expert_validator_id' => $assignment->expert_validator_id,
            'research_project_id' => $survey->project_id,
            'status' => $assignment->status,
        ], $request);

        return $assignment;
    }
}
