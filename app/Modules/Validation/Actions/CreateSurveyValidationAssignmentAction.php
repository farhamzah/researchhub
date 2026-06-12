<?php

namespace App\Modules\Validation\Actions;

use App\Models\ExpertValidator;
use App\Models\Survey;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateSurveyValidationAssignmentAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Survey $survey, SurveyValidationRound $round, array $attributes, ?Request $request = null): SurveyValidationAssignment
    {
        abort_unless($round->survey_id === $survey->getKey(), 404);
        Gate::forUser($user)->authorize('manageValidation', $survey);

        $validator = ExpertValidator::query()
            ->visibleTo($user)
            ->where('is_active', true)
            ->find($attributes['expert_validator_id'] ?? null);

        if (! $validator) {
            throw ValidationException::withMessages([
                'expert_validator_id' => 'Select an active expert validator visible to your account.',
            ]);
        }

        if ($round->assignments()->where('expert_validator_id', $validator->getKey())->exists()) {
            throw ValidationException::withMessages([
                'expert_validator_id' => 'This expert validator is already assigned to the validation round.',
            ]);
        }

        $assignment = SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->getKey(),
            'expert_validator_id' => $validator->getKey(),
            'role' => $attributes['role'] ?? null,
            'status' => SurveyValidationAssignment::STATUS_PENDING,
            'expires_at' => $attributes['expires_at'] ?? null,
            'created_by' => $user->getKey(),
        ]);

        $this->activityLogger->log('survey_validation_assignment.created', $user, $survey->project, $assignment, $this->metadata($assignment, $survey), $request);

        return $assignment;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(SurveyValidationAssignment $assignment, Survey $survey): array
    {
        return [
            'survey_validation_round_id' => $assignment->survey_validation_round_id,
            'survey_validation_assignment_id' => $assignment->getKey(),
            'survey_id' => $survey->getKey(),
            'expert_validator_id' => $assignment->expert_validator_id,
            'research_project_id' => $survey->project_id,
            'status' => $assignment->status,
        ];
    }
}
