<?php

namespace App\Modules\Validation\Actions;

use App\Models\ExpertValidatorProject;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteProjectValidatorAssignmentAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, ResearchProject $project, ExpertValidatorProject $assignment, ?Request $request = null): void
    {
        abort_unless($assignment->research_project_id === $project->getKey(), 404);

        Gate::forUser($user)->authorize('update', $project);

        if (! $user->can('projects.manage_validators') && ! $user->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'research_project_id' => 'You are not allowed to manage expert validators for this project.',
            ]);
        }

        $this->activityLogger->log('expert_validator.detached_from_project', $user, $project, $assignment, [
            'assignment_id' => $assignment->getKey(),
            'research_project_id' => $assignment->research_project_id,
            'expert_validator_id' => $assignment->expert_validator_id,
            'role' => $assignment->role,
            'status' => $assignment->status,
        ], $request);

        $assignment->delete();
    }
}
