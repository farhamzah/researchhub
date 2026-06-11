<?php

namespace App\Modules\Projects\Services;

use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Models\ResearchProject;
use Illuminate\Support\Collection;

class ProjectTimelineProgressService
{
    public function taskProgress(ProjectTimelineTask $task): int
    {
        if ($task->status === ProjectMilestone::STATUS_COMPLETED) {
            return 100;
        }

        if ($task->status === ProjectMilestone::STATUS_CANCELLED) {
            return 0;
        }

        return $this->clamp($task->progress_percentage);
    }

    public function isDelayed(ProjectMilestone|ProjectTimelineTask $item): bool
    {
        if (in_array($item->status, [ProjectMilestone::STATUS_COMPLETED, ProjectMilestone::STATUS_CANCELLED], true)) {
            return false;
        }

        return $item->planned_end_date !== null && $item->planned_end_date->isBefore(today());
    }

    /**
     * @return array<string, mixed>
     */
    public function projectSummary(ResearchProject $project): array
    {
        $tasks = $project->relationLoaded('timelineTasks')
            ? $project->timelineTasks
            : $project->timelineTasks()->get();

        $milestones = $project->relationLoaded('milestones')
            ? $project->milestones
            : $project->milestones()->get();

        return [
            'progress_percentage' => $this->weightedProgress($tasks),
            'total_milestones' => $milestones->count(),
            'total_tasks' => $tasks->count(),
            'active_tasks' => $tasks->reject(fn (ProjectTimelineTask $task): bool => $task->status === ProjectMilestone::STATUS_CANCELLED)->count(),
            'completed_tasks' => $tasks->where('status', ProjectMilestone::STATUS_COMPLETED)->count(),
            'delayed_tasks' => $tasks->filter(fn (ProjectTimelineTask $task): bool => $this->isDelayed($task))->count(),
            'delayed_milestones' => $milestones->filter(fn (ProjectMilestone $milestone): bool => $this->isDelayed($milestone))->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function milestoneSummary(ProjectMilestone $milestone): array
    {
        $tasks = $milestone->relationLoaded('tasks')
            ? $milestone->tasks
            : $milestone->tasks()->get();

        return [
            'progress_percentage' => $tasks->isNotEmpty()
                ? $this->weightedProgress($tasks)
                : ($milestone->status === ProjectMilestone::STATUS_COMPLETED ? 100 : 0),
            'total_tasks' => $tasks->count(),
            'active_tasks' => $tasks->reject(fn (ProjectTimelineTask $task): bool => $task->status === ProjectMilestone::STATUS_CANCELLED)->count(),
            'completed_tasks' => $tasks->where('status', ProjectMilestone::STATUS_COMPLETED)->count(),
            'delayed_tasks' => $tasks->filter(fn (ProjectTimelineTask $task): bool => $this->isDelayed($task))->count(),
            'is_delayed' => $this->isDelayed($milestone),
        ];
    }

    /**
     * @param  Collection<int, ProjectTimelineTask>  $tasks
     */
    public function weightedProgress(Collection $tasks): int
    {
        $activeTasks = $tasks->reject(fn (ProjectTimelineTask $task): bool => $task->status === ProjectMilestone::STATUS_CANCELLED);
        $totalWeight = $activeTasks->sum(fn (ProjectTimelineTask $task): float => max(0.0, (float) $task->weight));

        if ($totalWeight <= 0.0) {
            return 0;
        }

        $weightedProgress = $activeTasks->sum(function (ProjectTimelineTask $task): float {
            return $this->taskProgress($task) * max(0.0, (float) $task->weight);
        });

        return $this->clamp((int) round($weightedProgress / $totalWeight));
    }

    public function clamp(mixed $value): int
    {
        return max(0, min(100, (int) round((float) ($value ?? 0))));
    }
}
