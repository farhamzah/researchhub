<?php

namespace App\Http\Controllers;

use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Models\ResearchProject;
use App\Modules\Projects\Actions\CreateProjectMilestoneAction;
use App\Modules\Projects\Actions\CreateProjectTimelineTaskAction;
use App\Modules\Projects\Actions\DeleteProjectMilestoneAction;
use App\Modules\Projects\Actions\DeleteProjectTimelineTaskAction;
use App\Modules\Projects\Actions\UpdateProjectMilestoneAction;
use App\Modules\Projects\Actions\UpdateProjectTimelineTaskAction;
use App\Modules\Projects\Services\ProjectTimelineProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminProjectTimelineController extends Controller
{
    public function index(ResearchProject $researchProject, ProjectTimelineProgressService $progressService): View
    {
        Gate::authorize('viewTimeline', $researchProject);

        $researchProject->load([
            'owner',
            'activeMembers.user',
            'milestones.tasks.assignee',
            'timelineTasks.milestone',
            'timelineTasks.assignee',
        ]);

        return view('projects.admin.timeline.index', [
            'project' => $researchProject,
            'summary' => $progressService->projectSummary($researchProject),
            'milestoneSummaries' => $researchProject->milestones
                ->mapWithKeys(fn (ProjectMilestone $milestone): array => [$milestone->getKey() => $progressService->milestoneSummary($milestone)])
                ->all(),
            'statusOptions' => ProjectMilestone::STATUS_LABELS,
            'assignableUsers' => $this->assignableUsers($researchProject),
            'canManageTimeline' => request()->user()?->can('manageTimeline', $researchProject) ?? false,
        ]);
    }

    public function storeMilestone(ResearchProject $researchProject, Request $request, CreateProjectMilestoneAction $createMilestone): RedirectResponse
    {
        $createMilestone->handle($request->user(), $researchProject, $this->milestoneData($request), $request);

        return redirect()->route('admin.projects.timeline.index', ['researchProject' => $researchProject])->with('status', 'project-milestone-created');
    }

    public function updateMilestone(ResearchProject $researchProject, ProjectMilestone $milestone, Request $request, UpdateProjectMilestoneAction $updateMilestone): RedirectResponse
    {
        abort_unless($milestone->research_project_id === $researchProject->getKey(), 404);
        $updateMilestone->handle($request->user(), $milestone, $this->milestoneData($request), $request);

        return redirect()->route('admin.projects.timeline.index', ['researchProject' => $researchProject])->with('status', 'project-milestone-updated');
    }

    public function deleteMilestone(ResearchProject $researchProject, ProjectMilestone $milestone, Request $request, DeleteProjectMilestoneAction $deleteMilestone): RedirectResponse
    {
        abort_unless($milestone->research_project_id === $researchProject->getKey(), 404);
        $deleteMilestone->handle($request->user(), $milestone, $request);

        return redirect()->route('admin.projects.timeline.index', ['researchProject' => $researchProject])->with('status', 'project-milestone-deleted');
    }

    public function storeTask(ResearchProject $researchProject, Request $request, CreateProjectTimelineTaskAction $createTask): RedirectResponse
    {
        $createTask->handle($request->user(), $researchProject, $this->taskData($request, $researchProject), $request);

        return redirect()->route('admin.projects.timeline.index', ['researchProject' => $researchProject])->with('status', 'project-timeline-task-created');
    }

    public function updateTask(ResearchProject $researchProject, ProjectTimelineTask $task, Request $request, UpdateProjectTimelineTaskAction $updateTask): RedirectResponse
    {
        abort_unless($task->research_project_id === $researchProject->getKey(), 404);
        $updateTask->handle($request->user(), $task, $this->taskData($request, $researchProject), $request);

        return redirect()->route('admin.projects.timeline.index', ['researchProject' => $researchProject])->with('status', 'project-timeline-task-updated');
    }

    public function deleteTask(ResearchProject $researchProject, ProjectTimelineTask $task, Request $request, DeleteProjectTimelineTaskAction $deleteTask): RedirectResponse
    {
        abort_unless($task->research_project_id === $researchProject->getKey(), 404);
        $deleteTask->handle($request->user(), $task, $request);

        return redirect()->route('admin.projects.timeline.index', ['researchProject' => $researchProject])->with('status', 'project-timeline-task-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function milestoneData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'actual_start_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],
            'status' => ['required', 'string', Rule::in(ProjectMilestone::STATUSES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function taskData(Request $request, ResearchProject $project): array
    {
        $data = $request->validate([
            'project_milestone_id' => [
                'nullable',
                'string',
                Rule::exists('project_milestones', 'id')->where('research_project_id', $project->getKey()),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'actual_start_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],
            'status' => ['required', 'string', Rule::in(ProjectMilestone::STATUSES)],
            'progress_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'assigned_to' => ['nullable', 'string', Rule::exists('users', 'id')],
        ]);

        $this->ensureAssigneeBelongsToProject($project, $data['assigned_to'] ?? null);

        return $data;
    }

    private function ensureAssigneeBelongsToProject(ResearchProject $project, ?string $userId): void
    {
        if ($userId === null || $userId === $project->owner_id) {
            return;
        }

        if ($project->activeMembers()->where('user_id', $userId)->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'assigned_to' => 'Assignee must be the project owner or an active project member.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function assignableUsers(ResearchProject $project): array
    {
        $users = collect([$project->owner])
            ->filter()
            ->merge($project->activeMembers->pluck('user')->filter())
            ->unique(fn ($user): string => $user->getKey());

        return $users
            ->mapWithKeys(fn ($user): array => [$user->getKey() => $user->name.' <'.$user->email.'>'])
            ->all();
    }
}
