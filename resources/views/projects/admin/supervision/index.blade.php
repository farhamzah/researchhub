@php
    $statusClass = fn (string $status): string => match ($status) {
        \App\Models\SupervisionSession::STATUS_APPROVED,
        \App\Models\SupervisionSession::STATUS_CLOSED => 'bg-emerald-50 text-emerald-800',
        \App\Models\SupervisionSession::STATUS_REVISION_NEEDED => 'bg-amber-50 text-amber-800',
        \App\Models\SupervisionSession::STATUS_OPENED,
        \App\Models\SupervisionSession::STATUS_FEEDBACK_SUBMITTED => 'bg-blue-50 text-blue-800',
        default => 'bg-gray-100 text-gray-700',
    };
    $linkStatusClass = fn (string $status): string => match ($status) {
        \App\Models\SupervisionReviewLink::STATUS_SUBMITTED => 'bg-emerald-50 text-emerald-800',
        \App\Models\SupervisionReviewLink::STATUS_REVOKED,
        \App\Models\SupervisionReviewLink::STATUS_EXPIRED => 'bg-red-50 text-red-800',
        \App\Models\SupervisionReviewLink::STATUS_OPENED => 'bg-blue-50 text-blue-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supervision - ResearchHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">ResearchHub Admin</p>
                <h1 class="mt-2 text-3xl font-semibold">Supervision / Bimbingan</h1>
                <p class="mt-2 text-sm text-gray-600">{{ $project->title }}</p>
                <p class="mt-1 text-xs text-gray-500">Create guidance reports, share secure links, and track supervisor feedback.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ url('/admin/projects/research-projects') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Back to Projects
                </a>
                <a href="{{ route('admin.projects.validators.index', ['researchProject' => $project]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Supervisors / Validators
                </a>
            </div>
        </div>

        @if (session('generated_supervision_url'))
            <section class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-5 text-amber-950 shadow-sm">
                <h2 class="text-lg font-semibold">Copy this supervision review link now</h2>
                <p class="mt-1 text-sm">The raw link is shown once and is not stored in plain text.</p>
                <input readonly value="{{ session('generated_supervision_url') }}" class="mt-3 block w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-sm shadow-sm">
            </section>
        @endif

        @if (session('status'))
            <section class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
                {{ str_replace('-', ' ', session('status')) }}
            </section>
        @endif

        @if ($errors->any())
            <section class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Please review the form.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($canManageSupervision)
            <section class="mb-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Create Supervision Session</h2>
                <form method="POST" action="{{ route('admin.projects.supervision.sessions.store', ['researchProject' => $project]) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                    @csrf
                    <input type="hidden" name="status" value="{{ \App\Models\SupervisionSession::STATUS_DRAFT }}">
                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold" for="title">Title</label>
                        <input id="title" name="title" required value="{{ old('title') }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div>
                        <label class="text-sm font-semibold" for="meeting_type">Meeting Type</label>
                        <select id="meeting_type" name="meeting_type" required class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            @foreach ($meetingTypeLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('meeting_type', \App\Models\SupervisionSession::MEETING_REGULAR_GUIDANCE) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold" for="target_date">Target Date</label>
                        <input id="target_date" type="date" name="target_date" value="{{ old('target_date') }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold" for="agenda">Agenda</label>
                        <textarea id="agenda" name="agenda" rows="3" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('agenda') }}</textarea>
                    </div>
                    <div>
                        <label class="text-sm font-semibold" for="progress_report">Progress Report</label>
                        <textarea id="progress_report" name="progress_report" rows="4" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('progress_report') }}</textarea>
                    </div>
                    <div>
                        <label class="text-sm font-semibold" for="questions">Questions to Discuss</label>
                        <textarea id="questions" name="questions" rows="4" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('questions') }}</textarea>
                    </div>
                    <div>
                        <label class="text-sm font-semibold" for="requested_feedback">Requested Feedback</label>
                        <textarea id="requested_feedback" name="requested_feedback" rows="4" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('requested_feedback') }}</textarea>
                    </div>
                    <div>
                        <label class="text-sm font-semibold" for="next_plan">Next Plan</label>
                        <textarea id="next_plan" name="next_plan" rows="4" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('next_plan') }}</textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold" for="notes">Internal Notes</label>
                        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('notes') }}</textarea>
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">Create Session</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="space-y-6">
            @forelse ($sessions as $session)
                <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-semibold">{{ $session->title }}</h2>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass($session->status) }}">{{ $sessionStatusLabels[$session->status] ?? $session->status }}</span>
                            </div>
                            <p class="mt-2 text-sm text-gray-600">{{ $meetingTypeLabels[$session->meeting_type] ?? $session->meeting_type }}{{ $session->target_date ? ' - Target '.$session->target_date->format('Y-m-d') : '' }}</p>
                        </div>
                        <div class="w-full lg:w-[28rem]">
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Copy-Ready Summary</p>
                            <textarea readonly rows="7" class="block w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-xs leading-5 shadow-sm">{{ $session->copyReadySummary() }}</textarea>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="rounded-md border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Progress</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $session->progress_report ?: 'No progress report yet.' }}</p>
                        </div>
                        <div class="rounded-md border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Questions / Requested Feedback</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $session->questions ?: 'No questions listed.' }}</p>
                            <p class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ $session->requested_feedback ?: '' }}</p>
                        </div>
                    </div>

                    @if ($canManageSupervision)
                        <details class="mt-5 rounded-md border border-gray-200 p-4">
                            <summary class="cursor-pointer font-semibold">Update Session</summary>
                            <form method="POST" action="{{ route('admin.projects.supervision.sessions.update', ['researchProject' => $project, 'session' => $session]) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                                @csrf
                                @method('PUT')
                                <div class="md:col-span-2">
                                    <label class="text-sm font-semibold">Title</label>
                                    <input name="title" required value="{{ old('title', $session->title) }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                </div>
                                <div>
                                    <label class="text-sm font-semibold">Meeting Type</label>
                                    <select name="meeting_type" required class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                        @foreach ($meetingTypeLabels as $value => $label)
                                            <option value="{{ $value }}" @selected($session->meeting_type === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold">Status</label>
                                    <select name="status" required class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                        @foreach ($sessionStatusLabels as $value => $label)
                                            <option value="{{ $value }}" @selected($session->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold">Target Date</label>
                                    <input type="date" name="target_date" value="{{ $session->target_date?->format('Y-m-d') }}" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                </div>
                                <div>
                                    <label class="text-sm font-semibold">Next Plan</label>
                                    <textarea name="next_plan" rows="3" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ $session->next_plan }}</textarea>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="text-sm font-semibold">Agenda</label>
                                    <textarea name="agenda" rows="3" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ $session->agenda }}</textarea>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold">Progress Report</label>
                                    <textarea name="progress_report" rows="4" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ $session->progress_report }}</textarea>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold">Questions</label>
                                    <textarea name="questions" rows="4" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ $session->questions }}</textarea>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold">Requested Feedback</label>
                                    <textarea name="requested_feedback" rows="4" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ $session->requested_feedback }}</textarea>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold">Internal Notes</label>
                                    <textarea name="notes" rows="4" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ $session->notes }}</textarea>
                                </div>
                                <div class="md:col-span-2 flex justify-end">
                                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-black">Save Session</button>
                                </div>
                            </form>
                        </details>

                        <section class="mt-5 rounded-md border border-gray-200 p-4">
                            <h3 class="font-semibold">Generate Secure Supervisor Link</h3>
                            <form method="POST" action="{{ route('admin.projects.supervision.links.generate', ['researchProject' => $project, 'session' => $session]) }}" class="mt-4 grid gap-4 md:grid-cols-4">
                                @csrf
                                <div>
                                    <label class="text-sm font-semibold">Supervisor Registry</label>
                                    <select name="expert_validator_id" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                        <option value="">Manual recipient</option>
                                        @foreach ($availableValidators as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold">Recipient Name</label>
                                    <input name="recipient_name" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                </div>
                                <div>
                                    <label class="text-sm font-semibold">Recipient Role</label>
                                    <input name="recipient_role" placeholder="Promotor, Kopromotor, Reviewer" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                </div>
                                <div>
                                    <label class="text-sm font-semibold">Expires At</label>
                                    <input type="datetime-local" name="expires_at" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                </div>
                                <div class="md:col-span-4 flex justify-end">
                                    <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">Generate Link</button>
                                </div>
                            </form>
                        </section>
                    @endif

                    <section class="mt-5">
                        <h3 class="font-semibold">Review Links and Feedback</h3>
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <th class="py-3 pr-4">Supervisor</th>
                                        <th class="py-3 pr-4">Status</th>
                                        <th class="py-3 pr-4">Opened</th>
                                        <th class="py-3 pr-4">Submitted</th>
                                        <th class="py-3 pr-4">Decision</th>
                                        <th class="py-3 pr-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse ($session->reviewLinks as $reviewLink)
                                        <tr>
                                            <td class="py-3 pr-4">
                                                <p class="font-medium">{{ $reviewLink->recipientDisplayName() }}</p>
                                                <p class="text-xs text-gray-500">{{ $reviewLink->recipientDisplayRole() }}</p>
                                            </td>
                                            <td class="py-3 pr-4">
                                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $linkStatusClass($reviewLink->status) }}">{{ $linkStatusLabels[$reviewLink->status] ?? $reviewLink->status }}</span>
                                            </td>
                                            <td class="py-3 pr-4">{{ $reviewLink->opened_at?->format('Y-m-d H:i') ?? 'Not opened' }}</td>
                                            <td class="py-3 pr-4">{{ $reviewLink->submitted_at?->format('Y-m-d H:i') ?? 'Not submitted' }}</td>
                                            <td class="py-3 pr-4">{{ $reviewLink->feedback ? (\App\Models\SupervisionFeedback::DECISION_LABELS[$reviewLink->feedback->decision] ?? $reviewLink->feedback->decision) : 'No feedback' }}</td>
                                            <td class="py-3 pr-4">
                                                @if ($canManageSupervision && ! $reviewLink->isSubmitted() && ! $reviewLink->isRevoked())
                                                    <form method="POST" action="{{ route('admin.projects.supervision.links.revoke', ['researchProject' => $project, 'reviewLink' => $reviewLink]) }}">
                                                        @csrf
                                                        <button class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50" onclick="return confirm('Revoke this supervision link? This cannot be undone.')">Revoke</button>
                                                    </form>
                                                @else
                                                    <span class="text-xs text-gray-500">No action</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @if ($reviewLink->feedback)
                                            <tr>
                                                <td colspan="6" class="bg-gray-50 py-4 pr-4">
                                                    <div class="grid gap-3 md:grid-cols-2">
                                                        <div>
                                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">General Feedback</p>
                                                            <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $reviewLink->feedback->general_feedback }}</p>
                                                        </div>
                                                        <div>
                                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Recommended Next Steps</p>
                                                            <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $reviewLink->feedback->recommended_next_steps ?: 'No recommendation.' }}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-6 text-center text-sm text-gray-500">No supervision review links generated yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </article>
            @empty
                <section class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center shadow-sm">
                    <h2 class="text-xl font-semibold">No supervision sessions yet.</h2>
                    <p class="mt-2 text-sm text-gray-600">Create a session to prepare a supervisor discussion and secure review link.</p>
                </section>
            @endforelse
        </section>
    </main>
</body>
</html>
