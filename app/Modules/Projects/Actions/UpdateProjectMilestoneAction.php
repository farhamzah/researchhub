<?php

namespace App\Modules\Projects\Actions;

use App\Models\ProjectMilestone;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UpdateProjectMilestoneAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, ProjectMilestone $milestone, array $attributes, ?Request $request = null): ProjectMilestone
    {
        $milestone->loadMissing('project');
        Gate::forUser($user)->authorize('manageTimeline', $milestone->project);

        $milestone->fill([
            'title' => (string) $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'planned_start_date' => $attributes['planned_start_date'] ?? null,
            'planned_end_date' => $attributes['planned_end_date'] ?? null,
            'actual_start_date' => $attributes['actual_start_date'] ?? null,
            'actual_end_date' => $attributes['actual_end_date'] ?? null,
            'status' => $attributes['status'] ?? ProjectMilestone::STATUS_NOT_STARTED,
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            'settings' => $attributes['settings'] ?? null,
            'updated_by' => $user->getKey(),
        ])->save();

        $this->activityLogger->log('project_milestone.updated', $user, $milestone->project, $milestone, [
            'research_project_id' => $milestone->research_project_id,
            'project_milestone_id' => $milestone->getKey(),
            'status' => $milestone->status,
        ], $request);

        return $milestone->fresh(['tasks']);
    }
}
