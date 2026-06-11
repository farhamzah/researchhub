<?php

namespace App\Modules\Projects\Actions;

use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Projects\Services\ProjectTimelineProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateProjectTimelineTaskAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ProjectTimelineProgressService $progressService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, ResearchProject $project, array $attributes, ?Request $request = null): ProjectTimelineTask
    {
        Gate::forUser($user)->authorize('manageTimeline', $project);
        $this->ensureMilestoneBelongsToProject($project, $attributes['project_milestone_id'] ?? null);

        $task = $project->timelineTasks()->create([
            'project_milestone_id' => $attributes['project_milestone_id'] ?? null,
            'title' => (string) $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'planned_start_date' => $attributes['planned_start_date'] ?? null,
            'planned_end_date' => $attributes['planned_end_date'] ?? null,
            'actual_start_date' => $attributes['actual_start_date'] ?? null,
            'actual_end_date' => $attributes['actual_end_date'] ?? null,
            'status' => $attributes['status'] ?? ProjectMilestone::STATUS_NOT_STARTED,
            'progress_percentage' => $this->progressService->clamp($attributes['progress_percentage'] ?? 0),
            'weight' => (float) ($attributes['weight'] ?? 1),
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            'assigned_to' => $attributes['assigned_to'] ?? null,
            'settings' => $attributes['settings'] ?? null,
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        $this->activityLogger->log('project_timeline_task.created', $user, $project, $task, [
            'research_project_id' => $project->getKey(),
            'project_milestone_id' => $task->project_milestone_id,
            'project_timeline_task_id' => $task->getKey(),
            'status' => $task->status,
            'progress_percentage' => $task->progress_percentage,
        ], $request);

        return $task;
    }

    private function ensureMilestoneBelongsToProject(ResearchProject $project, ?string $milestoneId): void
    {
        if ($milestoneId !== null && $project->milestones()->whereKey($milestoneId)->doesntExist()) {
            throw ValidationException::withMessages([
                'project_milestone_id' => 'Selected milestone does not belong to this project.',
            ]);
        }
    }
}
