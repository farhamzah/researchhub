<?php

namespace App\Modules\Projects\Actions;

use App\Models\ProjectMilestone;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeleteProjectMilestoneAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, ProjectMilestone $milestone, ?Request $request = null): void
    {
        $milestone->loadMissing('project');
        Gate::forUser($user)->authorize('manageTimeline', $milestone->project);

        $metadata = [
            'research_project_id' => $milestone->research_project_id,
            'project_milestone_id' => $milestone->getKey(),
            'status' => $milestone->status,
        ];

        $milestone->tasks()->update(['project_milestone_id' => null]);
        $milestone->delete();

        $this->activityLogger->log('project_milestone.deleted', $user, $milestone->project, $milestone, $metadata, $request);
    }
}
