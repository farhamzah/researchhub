<?php

namespace App\Modules\Validation\Actions;

use App\Models\ExpertValidatorProject;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateProjectValidatorAssignmentAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, ResearchProject $project, ExpertValidatorProject $assignment, array $attributes, ?Request $request = null): ExpertValidatorProject
    {
        abort_unless($assignment->research_project_id === $project->getKey(), 404);
        $this->authorizeProject($user, $project);

        $assignment->update([
            'role' => $this->role($attributes['role'] ?? $assignment->role),
            'expertise_scope' => $attributes['expertise_scope'] ?? null,
            'status' => $this->status($attributes['status'] ?? $assignment->status),
            'invited_at' => $attributes['invited_at'] ?? null,
            'accepted_at' => $attributes['accepted_at'] ?? null,
            'notes' => $attributes['notes'] ?? null,
        ]);

        $this->activityLogger->log('expert_validator.project_assignment_updated', $user, $project, $assignment, $this->metadata($assignment), $request);

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
