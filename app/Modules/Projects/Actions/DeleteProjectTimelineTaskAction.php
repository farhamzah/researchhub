<?php

namespace App\Modules\Projects\Actions;

use App\Models\ProjectTimelineTask;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeleteProjectTimelineTaskAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, ProjectTimelineTask $task, ?Request $request = null): void
    {
        $task->loadMissing('project');
        Gate::forUser($user)->authorize('manageTimeline', $task->project);

        $metadata = [
            'research_project_id' => $task->research_project_id,
            'project_milestone_id' => $task->project_milestone_id,
            'project_timeline_task_id' => $task->getKey(),
            'status' => $task->status,
            'progress_percentage' => $task->progress_percentage,
        ];

        $task->delete();

        $this->activityLogger->log('project_timeline_task.deleted', $user, $task->project, $task, $metadata, $request);
    }
}
