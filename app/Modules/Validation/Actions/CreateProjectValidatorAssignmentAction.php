<?php

namespace App\Modules\Validation\Actions;

use App\Models\ExpertValidator;
use App\Models\ExpertValidatorProject;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateProjectValidatorAssignmentAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, ResearchProject $project, array $attributes, ?Request $request = null): ExpertValidatorProject
    {
        $this->authorizeProject($user, $project);

        $validator = ExpertValidator::query()
            ->visibleTo($user)
            ->where('is_active', true)
            ->find($attributes['expert_validator_id'] ?? null);

        if (! $validator) {
            throw ValidationException::withMessages([
                'expert_validator_id' => 'Select an active expert validator visible to your account.',
            ]);
        }

        $role = $this->role($attributes['role'] ?? null);

        if ($project->expertValidatorAssignments()
            ->where('expert_validator_id', $validator->getKey())
            ->where('role', $role)
            ->exists()) {
            throw ValidationException::withMessages([
                'expert_validator_id' => 'This expert validator is already assigned to the project with the selected role.',
            ]);
        }

        $assignment = ExpertValidatorProject::create([
            'research_project_id' => $project->getKey(),
            'expert_validator_id' => $validator->getKey(),
            'role' => $role,
            'expertise_scope' => $attributes['expertise_scope'] ?? null,
            'status' => $this->status($attributes['status'] ?? ExpertValidatorProject::STATUS_INVITED),
            'invited_at' => $attributes['invited_at'] ?? null,
            'accepted_at' => $attributes['accepted_at'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'created_by' => $user->getKey(),
        ]);

        $this->activityLogger->log('expert_validator.assigned_to_project', $user, $project, $assignment, $this->metadata($assignment), $request);

        return $assignment;
    }

    private function authorizeProject(User $user, ResearchProject $project): void
    {
        Gate::forUser($user)->authorize('update', $project);

        if (! $user->can('projects.manage_validators') && ! $user->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'research_project_id' => 'You are not allowed to manage expert validators for this project.',
            ]);
        }
    }

    private function role(mixed $role): string
    {
        $role = (string) $role;

        if (! in_array($role, ExpertValidatorProject::ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => 'Select a valid expert validator role.',
            ]);
        }

        return $role;
    }

    private function status(mixed $status): string
    {
        $status = (string) $status;

        if (! in_array($status, ExpertValidatorProject::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Select a valid expert validator status.',
            ]);
        }

        return $status;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(ExpertValidatorProject $assignment): array
    {
        return [
            'assignment_id' => $assignment->getKey(),
            'research_project_id' => $assignment->research_project_id,
            'expert_validator_id' => $assignment->expert_validator_id,
            'role' => $assignment->role,
            'status' => $assignment->status,
        ];
    }
}
