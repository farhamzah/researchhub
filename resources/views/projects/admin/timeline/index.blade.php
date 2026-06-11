@php
    use App\Models\ProjectMilestone;

    $statusBadge = fn (string $status): string => match ($status) {
        ProjectMilestone::STATUS_COMPLETED => 'bg-emerald-50 text-emerald-800',
        ProjectMilestone::STATUS_IN_PROGRESS => 'bg-blue-50 text-blue-800',
        ProjectMilestone::STATUS_DELAYED => 'bg-amber-50 text-amber-800',
        ProjectMilestone::STATUS_CANCELLED => 'bg-gray-100 text-gray-600',
        default => 'bg-gray-50 text-gray-700',
    };
    $formatDate = fn ($date): string => $date?->format('Y-m-d') ?? 'Not set';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Project Timeline - ResearchHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">ResearchHub Projects</p>
                <h1 class="mt-2 text-3xl font-semibold">Project Timeline</h1>
                <p class="mt-2 text-sm text-gray-600">{{ $project->title }}</p>
            </div>
            <a href="{{ route('filament.admin.resources.research-projects.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                Back to Projects
            </a>
        </div>

        @if (session('status'))
            <section class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
                Timeline updated.
            </section>
        @endif

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Progress Summary</h2>
                    <p class="mt-1 text-sm text-gray-600">Weighted progress excludes cancelled tasks.</p>
                </div>
                <div class="text-4xl font-semibold text-emerald-700">{{ $summary['progress_percentage'] }}%</div>
            </div>
            <div class="mt-5 h-3 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-emerald-600" style="width: {{ $summary['progress_percentage'] }}%"></div>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Milestones</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $summary['total_milestones'] }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tasks</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $summary['total_tasks'] }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Completed</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $summary['completed_tasks'] }}</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Delayed Tasks</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-900">{{ $summary['delayed_tasks'] }}</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Delayed Milestones</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-900">{{ $summary['delayed_milestones'] }}</p>
                </div>
            </div>
        </section>

        @if ($canManageTimeline)
            <section class="mb-6 grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold">Create Milestone</h2>
                    <form method="POST" action="{{ route('admin.projects.timeline.milestones.store', ['researchProject' => $project]) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div class="sm:col-span-2">
                            <label for="milestone_title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input id="milestone_title" name="title" required value="{{ old('title') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="milestone_status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="milestone_status" name="status" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="milestone_sort_order" class="block text-sm font-medium text-gray-700">Sort order</label>
                            <input id="milestone_sort_order" name="sort_order" type="number" min="0" value="0" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="milestone_planned_start_date" class="block text-sm font-medium text-gray-700">Planned start</label>
                            <input id="milestone_planned_start_date" name="planned_start_date" type="date" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="milestone_planned_end_date" class="block text-sm font-medium text-gray-700">Planned end</label>
                            <input id="milestone_planned_end_date" name="planned_end_date" type="date" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="milestone_actual_start_date" class="block text-sm font-medium text-gray-700">Actual start</label>
                            <input id="milestone_actual_start_date" name="actual_start_date" type="date" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="milestone_actual_end_date" class="block text-sm font-medium text-gray-700">Actual end</label>
                            <input id="milestone_actual_end_date" name="actual_end_date" type="date" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="milestone_description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="milestone_description" name="description" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"></textarea>
                        </div>
                        <button type="submit" class="sm:col-span-2 rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                            Add Milestone
                        </button>
                    </form>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold">Create Task</h2>
                    <form method="POST" action="{{ route('admin.projects.timeline.tasks.store', ['researchProject' => $project]) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div class="sm:col-span-2">
                            <label for="task_title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input id="task_title" name="title" required value="{{ old('title') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="task_milestone" class="block text-sm font-medium text-gray-700">Milestone</label>
                            <select id="task_milestone" name="project_milestone_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                <option value="">No milestone</option>
                                @foreach ($project->milestones as $milestone)
                                    <option value="{{ $milestone->id }}">{{ $milestone->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="task_assigned_to" class="block text-sm font-medium text-gray-700">Assignee</label>
                            <select id="task_assigned_to" name="assigned_to" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                <option value="">Unassigned</option>
                                @foreach ($assignableUsers as $userId => $label)
                                    <option value="{{ $userId }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('assigned_to')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="task_status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="task_status" name="status" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="task_progress" class="block text-sm font-medium text-gray-700">Progress %</label>
                            <input id="task_progress" name="progress_percentage" type="number" min="0" max="100" value="0" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="task_weight" class="block text-sm font-medium text-gray-700">Weight</label>
                            <input id="task_weight" name="weight" type="number" min="0" step="0.01" value="1" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="task_sort_order" class="block text-sm font-medium text-gray-700">Sort order</label>
                            <input id="task_sort_order" name="sort_order" type="number" min="0" value="0" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="task_planned_start_date" class="block text-sm font-medium text-gray-700">Planned start</label>
                            <input id="task_planned_start_date" name="planned_start_date" type="date" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="task_planned_end_date" class="block text-sm font-medium text-gray-700">Planned end</label>
                            <input id="task_planned_end_date" name="planned_end_date" type="date" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="task_actual_start_date" class="block text-sm font-medium text-gray-700">Actual start</label>
                            <input id="task_actual_start_date" name="actual_start_date" type="date" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="task_actual_end_date" class="block text-sm font-medium text-gray-700">Actual end</label>
                            <input id="task_actual_end_date" name="actual_end_date" type="date" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="task_description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="task_description" name="description" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"></textarea>
                        </div>
                        <button type="submit" class="sm:col-span-2 rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-600">
                            Add Task
                        </button>
                    </form>
                </div>
            </section>
        @endif

        <section class="space-y-6">
            @forelse ($project->milestones as $milestone)
                @php $milestoneSummary = $milestoneSummaries[$milestone->id]; @endphp
                <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-semibold">{{ $milestone->title }}</h2>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge($milestone->status) }}">{{ $statusOptions[$milestone->status] ?? $milestone->status }}</span>
                                @if ($milestoneSummary['is_delayed'])
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-900">Delayed</span>
                                @endif
                            </div>
                            @if ($milestone->description)
                                <p class="mt-2 text-sm text-gray-600">{{ $milestone->description }}</p>
                            @endif
                        </div>
                        <div class="min-w-36 text-right">
                            <p class="text-2xl font-semibold text-emerald-700">{{ $milestoneSummary['progress_percentage'] }}%</p>
                            <p class="text-xs text-gray-500">milestone progress</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p class="font-semibold text-gray-500">Planned</p>
                            <p>{{ $formatDate($milestone->planned_start_date) }} to {{ $formatDate($milestone->planned_end_date) }}</p>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-500">Actual</p>
                            <p>{{ $formatDate($milestone->actual_start_date) }} to {{ $formatDate($milestone->actual_end_date) }}</p>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-500">Tasks</p>
                            <p>{{ $milestoneSummary['completed_tasks'] }} / {{ $milestoneSummary['active_tasks'] }} complete</p>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-500">Delayed tasks</p>
                            <p>{{ $milestoneSummary['delayed_tasks'] }}</p>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <th class="py-3 pr-4">Task</th>
                                    <th class="py-3 pr-4">Status</th>
                                    <th class="py-3 pr-4">Progress</th>
                                    <th class="py-3 pr-4">Weight</th>
                                    <th class="py-3 pr-4">Planned</th>
                                    <th class="py-3 pr-4">Actual</th>
                                    <th class="py-3 pr-4">Assignee</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($milestone->tasks as $task)
                                    @php
                                        $taskDelayed = ! in_array($task->status, [ProjectMilestone::STATUS_COMPLETED, ProjectMilestone::STATUS_CANCELLED], true)
                                            && $task->planned_end_date !== null
                                            && $task->planned_end_date->isBefore(today());
                                    @endphp
                                    <tr>
                                        <td class="py-3 pr-4 font-medium">{{ $task->title }}</td>
                                        <td class="py-3 pr-4">
                                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge($task->status) }}">{{ $statusOptions[$task->status] ?? $task->status }}</span>
                                            @if ($taskDelayed)
                                                <span class="ml-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-900">Delayed</span>
                                            @endif
                                        </td>
                                        <td class="py-3 pr-4">{{ $task->status === ProjectMilestone::STATUS_COMPLETED ? 100 : $task->progress_percentage }}%</td>
                                        <td class="py-3 pr-4">{{ $task->weight }}</td>
                                        <td class="py-3 pr-4">{{ $formatDate($task->planned_start_date) }} to {{ $formatDate($task->planned_end_date) }}</td>
                                        <td class="py-3 pr-4">{{ $formatDate($task->actual_start_date) }} to {{ $formatDate($task->actual_end_date) }}</td>
                                        <td class="py-3 pr-4">{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-6 text-center text-sm text-gray-500">No tasks in this milestone yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($canManageTimeline)
                        <details class="mt-6 rounded-md border border-gray-200 bg-gray-50">
                            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700">Edit milestone</summary>
                            <form method="POST" action="{{ route('admin.projects.timeline.milestones.update', ['researchProject' => $project, 'milestone' => $milestone]) }}" class="grid gap-4 border-t border-gray-200 p-4 sm:grid-cols-2">
                                @csrf
                                @method('PUT')
                                <input name="title" value="{{ $milestone->title }}" required class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                <select name="status" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($milestone->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input name="planned_start_date" type="date" value="{{ $milestone->planned_start_date?->format('Y-m-d') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                <input name="planned_end_date" type="date" value="{{ $milestone->planned_end_date?->format('Y-m-d') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                <input name="actual_start_date" type="date" value="{{ $milestone->actual_start_date?->format('Y-m-d') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                <input name="actual_end_date" type="date" value="{{ $milestone->actual_end_date?->format('Y-m-d') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                <input name="sort_order" type="number" min="0" value="{{ $milestone->sort_order }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                <textarea name="description" rows="2" class="sm:col-span-2 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ $milestone->description }}</textarea>
                                <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Save Milestone</button>
                            </form>
                            <form method="POST" action="{{ route('admin.projects.timeline.milestones.delete', ['researchProject' => $project, 'milestone' => $milestone]) }}" class="border-t border-gray-200 p-4">
                                @csrf
                                @method('DELETE')
                                <button type="submit" onclick="return confirm('Delete this milestone? Tasks will remain in the project without a milestone.')" class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 shadow-sm hover:bg-red-50">
                                    Delete Milestone
                                </button>
                            </form>
                        </details>
                    @endif
                </article>
            @empty
                <section class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center shadow-sm">
                    <h2 class="text-xl font-semibold">No milestones yet</h2>
                    <p class="mt-2 text-sm text-gray-600">Create a milestone to start comparing planning and actual progress.</p>
                </section>
            @endforelse
        </section>

        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">All Tasks</h2>
            <p class="mt-1 text-sm text-gray-600">Flattened task list across milestones and unassigned tasks.</p>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="py-3 pr-4">Task</th>
                            <th class="py-3 pr-4">Milestone</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3 pr-4">Progress</th>
                            <th class="py-3 pr-4">Weight</th>
                            <th class="py-3 pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($project->timelineTasks as $task)
                            <tr>
                                <td class="py-3 pr-4 font-medium">{{ $task->title }}</td>
                                <td class="py-3 pr-4">{{ $task->milestone?->title ?? 'No milestone' }}</td>
                                <td class="py-3 pr-4"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge($task->status) }}">{{ $statusOptions[$task->status] ?? $task->status }}</span></td>
                                <td class="py-3 pr-4">{{ $task->status === ProjectMilestone::STATUS_COMPLETED ? 100 : $task->progress_percentage }}%</td>
                                <td class="py-3 pr-4">{{ $task->weight }}</td>
                                <td class="py-3 pr-4">
                                    @if ($canManageTimeline)
                                        <details>
                                            <summary class="cursor-pointer text-sm font-semibold text-emerald-700">Edit</summary>
                                            <form method="POST" action="{{ route('admin.projects.timeline.tasks.update', ['researchProject' => $project, 'task' => $task]) }}" class="mt-3 grid min-w-[520px] gap-3 rounded-md border border-gray-200 bg-gray-50 p-4 sm:grid-cols-2">
                                                @csrf
                                                @method('PUT')
                                                <input name="title" value="{{ $task->title }}" required class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                                <select name="project_milestone_id" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                                    <option value="">No milestone</option>
                                                    @foreach ($project->milestones as $milestone)
                                                        <option value="{{ $milestone->id }}" @selected($task->project_milestone_id === $milestone->id)>{{ $milestone->title }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="status" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                                    @foreach ($statusOptions as $value => $label)
                                                        <option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="assigned_to" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                                    <option value="">Unassigned</option>
                                                    @foreach ($assignableUsers as $userId => $label)
                                                        <option value="{{ $userId }}" @selected($task->assigned_to === $userId)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <input name="progress_percentage" type="number" min="0" max="100" value="{{ $task->progress_percentage }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                                <input name="weight" type="number" min="0" step="0.01" value="{{ $task->weight }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                                <input name="planned_start_date" type="date" value="{{ $task->planned_start_date?->format('Y-m-d') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                                <input name="planned_end_date" type="date" value="{{ $task->planned_end_date?->format('Y-m-d') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                                <input name="actual_start_date" type="date" value="{{ $task->actual_start_date?->format('Y-m-d') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                                <input name="actual_end_date" type="date" value="{{ $task->actual_end_date?->format('Y-m-d') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                                <input name="sort_order" type="number" min="0" value="{{ $task->sort_order }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                                <textarea name="description" rows="2" class="sm:col-span-2 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ $task->description }}</textarea>
                                                <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Save Task</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.projects.timeline.tasks.delete', ['researchProject' => $project, 'task' => $task]) }}" class="mt-2">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" onclick="return confirm('Delete this task?')" class="rounded-md border border-red-300 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-50">Delete Task</button>
                                            </form>
                                        </details>
                                    @else
                                        <span class="text-xs text-gray-500">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-sm text-gray-500">No tasks yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
