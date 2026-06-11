<?php

namespace App\Modules\Projects\Actions;

use App\Models\ProjectMilestone;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CreateProjectMilestoneAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, ResearchProject $project, array $attributes, ?Request $request = null): ProjectMilestone
    {
        Gate::forUser($user)->authorize('manageTimeline', $project);

        $milestone = $project->milestones()->create([
            'title' => (string) $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'planned_start_date' => $attributes['planned_start_date'] ?? null,
            'planned_end_date' => $attributes['planned_end_date'] ?? null,
            'actual_start_date' => $attributes['actual_start_date'] ?? null,
            'actual_end_date' => $attributes['actual_end_date'] ?? null,
            'status' => $attributes['status'] ?? ProjectMilestone::STATUS_NOT_STARTED,
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            'settings' => $attributes['settings'] ?? null,
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        $this->activityLogger->log('project_milestone.created', $user, $project, $milestone, [
            'research_project_id' => $project->getKey(),
            'project_milestone_id' => $milestone->getKey(),
            'status' => $milestone->status,
        ], $request);

        return $milestone;
    }
}
