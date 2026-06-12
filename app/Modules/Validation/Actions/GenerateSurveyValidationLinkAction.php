<?php

namespace App\Modules\Validation\Actions;

use App\Models\Survey;
use App\Models\SurveyValidationAssignment;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Validation\DTOs\SurveyValidationLinkGenerationResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GenerateSurveyValidationLinkAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Survey $survey, SurveyValidationAssignment $assignment, ?Request $request = null): SurveyValidationLinkGenerationResult
    {
        $assignment->loadMissing('round');
        abort_unless($assignment->round?->survey_id === $survey->getKey(), 404);
        Gate::forUser($user)->authorize('manageValidation', $survey);

        if ($assignment->isSubmitted()) {
            throw ValidationException::withMessages([
                'assignment' => 'Submitted validation assignments cannot receive a new link.',
            ]);
        }

        $rawToken = Str::random(64);

        $assignment->forceFill([
            'token_hash' => SurveyValidationAssignment::hashToken($rawToken),
            'token_created_at' => now(),
            'opened_at' => null,
            'revoked_at' => null,
            'status' => SurveyValidationAssignment::STATUS_LINK_GENERATED,
        ])->save();

        $this->activityLogger->log('survey_validation_link.generated', $user, $survey->project, $assignment, [
            'survey_validation_round_id' => $assignment->survey_validation_round_id,
            'survey_validation_assignment_id' => $assignment->getKey(),
            'survey_id' => $survey->getKey(),
            'expert_validator_id' => $assignment->expert_validator_id,
            'research_project_id' => $survey->project_id,
            'status' => $assignment->status,
        ], $request);

        return new SurveyValidationLinkGenerationResult(
            assignment: $assignment,
            rawToken: $rawToken,
            url: route('validation.survey.show', ['token' => $rawToken]),
        );
    }
}
