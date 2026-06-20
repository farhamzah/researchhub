@php
    $badge = fn (string $status): string => match ($status) {
        'open', 'submitted', 'completed', 'accepted', 'revised' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'opened', 'in_progress', 'draft', 'pending' => 'border-blue-200 bg-blue-50 text-blue-900',
        'needs_follow_up' => 'border-amber-200 bg-amber-50 text-amber-900',
        'revoked', 'closed', 'rejected_with_reason' => 'border-red-200 bg-red-50 text-red-900',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supervisor Review - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">Supervisor Instrument Review</p>
                    <h1 class="mt-2 text-3xl font-semibold">{{ $survey->title }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Qualitative pre-validation review for supervisors/promotors. These comments are separate from expert validation scores, Aiken's V, CVI, and respondent analysis.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.surveys.builder.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Builder</a>
                    <a href="{{ route('admin.surveys.validation.index', ['survey' => $survey]) }}" class="rounded-md border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">Expert Validation</a>
                    <a href="{{ route('admin.surveys.preflight.index', ['survey' => $survey]) }}" class="rounded-md border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-900 shadow-sm hover:bg-amber-50">Preflight QA</a>
                </div>
            </div>

            @if (session('generated_supervisor_review_url'))
                <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-950">Supervisor review link generated. Raw token is shown once.</p>
                    <input readonly value="{{ session('generated_supervisor_review_url') }}" class="mt-3 block w-full rounded-md border border-amber-300 bg-white px-3 py-2 font-mono text-xs">
                </div>
            @endif
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Create Review Round</h2>
            <form method="POST" action="{{ route('admin.surveys.supervisor-review.rounds.store', ['survey' => $survey]) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Round title</span>
                    <input name="title" required value="Supervisor Review - {{ $survey->title }}" class="mt-1 block w-full rounded-md border-slate-300">
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Status</span>
                    <select name="status" class="mt-1 block w-full rounded-md border-slate-300">
                        @foreach ($roundStatuses as $value => $label)
                            <option value="{{ $value }}" @selected($value === 'draft')>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Due date</span>
                    <input type="date" name="due_date" class="mt-1 block w-full rounded-md border-slate-300">
                </label>
                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-slate-700">Review purpose</span>
                    <textarea name="purpose" rows="3" class="mt-1 block w-full rounded-md border-slate-300">Pre-validation supervisor review before expert validation.</textarea>
                </label>
                <div class="md:col-span-2">
                    <button class="rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-600">Create Round</button>
                </div>
            </form>
        </section>

        @forelse ($rounds as $round)
            <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold">{{ $round->title }}</h2>
                            <span class="rounded-full border px-2 py-1 text-xs font-semibold {{ $badge($round->status) }}">{{ str($round->status)->replace('_', ' ')->title() }}</span>
                            @if ($round->snapshot_taken_at)
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-700">Snapshot {{ $round->snapshot_taken_at->format('Y-m-d H:i') }}</span>
                            @endif
                            @if ($instrumentChanged($round))
                                <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900">Instrument changed after snapshot</span>
                            @endif
                        </div>
                        <p class="mt-2 text-sm text-slate-600">{{ $round->purpose ?: 'No purpose provided.' }}</p>
                    </div>
                    <a target="_blank" href="{{ route('admin.surveys.supervisor-review.report', ['survey' => $survey, 'round' => $round]) }}" class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm hover:bg-blue-50">Printable Report</a>
                </div>

                <form method="POST" action="{{ route('admin.surveys.supervisor-review.rounds.update', ['survey' => $survey, 'round' => $round]) }}" class="mt-5 grid gap-3 md:grid-cols-4">
                    @csrf
                    @method('PUT')
                    <input name="title" value="{{ $round->title }}" class="rounded-md border-slate-300">
                    <input type="date" name="due_date" value="{{ $round->due_date?->format('Y-m-d') }}" class="rounded-md border-slate-300">
                    <select name="status" class="rounded-md border-slate-300">
                        @foreach ($roundStatuses as $value => $label)
                            <option value="{{ $value }}" @selected($round->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Update Round</button>
                    <textarea name="purpose" rows="2" class="md:col-span-4 rounded-md border-slate-300">{{ $round->purpose }}</textarea>
                </form>

                <div class="mt-6 grid gap-6 lg:grid-cols-3">
                    <div class="lg:col-span-1">
                        <h3 class="font-semibold">Add Supervisor</h3>
                        <form method="POST" action="{{ route('admin.surveys.supervisor-review.reviewers.store', ['survey' => $survey, 'round' => $round]) }}" class="mt-3 space-y-3">
                            @csrf
                            <input name="supervisor_name" placeholder="Supervisor name" required class="block w-full rounded-md border-slate-300">
                            <input name="supervisor_email" placeholder="email optional" class="block w-full rounded-md border-slate-300">
                            <input name="supervisor_code" placeholder="SPV-1" class="block w-full rounded-md border-slate-300">
                            <input name="role" placeholder="Promotor / Co-promotor" class="block w-full rounded-md border-slate-300">
                            <input type="datetime-local" name="expires_at" class="block w-full rounded-md border-slate-300">
                            <button class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Add Supervisor</button>
                        </form>
                    </div>

                    <div class="lg:col-span-2">
                        <h3 class="font-semibold">Review Progress</h3>
                        <div class="mt-3 divide-y divide-slate-200 rounded-lg border border-slate-200">
                            @forelse ($round->reviewers as $reviewer)
                                <div class="p-4">
                                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                        <div>
                                            <p class="font-semibold">{{ $reviewer->supervisor_name }}</p>
                                            <p class="text-sm text-slate-600">{{ $reviewer->supervisor_code ?: 'No code' }} · {{ $reviewer->role ?: 'Supervisor' }}</p>
                                            <p class="mt-1"><span class="rounded-full border px-2 py-1 text-xs font-semibold {{ $badge($reviewer->status) }}">{{ $reviewerStatuses[$reviewer->status] ?? str($reviewer->status)->title() }}</span></p>
                                            @if ($reviewer->final_decision)
                                                <p class="mt-2 text-sm text-slate-700">Decision: <span class="font-semibold">{{ $decisions[$reviewer->final_decision] ?? $reviewer->final_decision }}</span></p>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <form method="POST" action="{{ route('admin.surveys.supervisor-review.reviewers.generate-link', ['survey' => $survey, 'reviewer' => $reviewer]) }}">
                                                @csrf
                                                <button class="rounded-md border border-indigo-300 bg-white px-3 py-2 text-xs font-semibold text-indigo-800 shadow-sm hover:bg-indigo-50">Generate Link</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.surveys.supervisor-review.reviewers.revoke-link', ['survey' => $survey, 'reviewer' => $reviewer]) }}">
                                                @csrf
                                                <button class="rounded-md border border-red-300 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-50">Revoke</button>
                                            </form>
                                        </div>
                                    </div>
                                    @if ($reviewer->comments->isNotEmpty())
                                        <div class="mt-4 space-y-2">
                                            @foreach ($reviewer->comments as $comment)
                                                <div class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                                                    <p class="font-semibold">{{ str($comment->comment_type)->title() }} · {{ $comment->target_key ?: $comment->target_label }}</p>
                                                    <p class="mt-1">{{ $comment->comment }}</p>
                                                    @if ($comment->suggested_revision)
                                                        <p class="mt-1 text-slate-600">Suggested: {{ $comment->suggested_revision }}</p>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="p-4 text-sm text-slate-600">No supervisors added yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <h3 class="font-semibold">Revision Matrix</h3>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Item/Section</th>
                                    <th class="px-3 py-2">Supervisor</th>
                                    <th class="px-3 py-2">Comment</th>
                                    <th class="px-3 py-2">Researcher Response</th>
                                    <th class="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($round->revisions as $revision)
                                    <tr>
                                        <td class="px-3 py-3 align-top font-semibold">{{ $revision->item_label }}</td>
                                        <td class="px-3 py-3 align-top">{{ $revision->supervisor_code }}</td>
                                        <td class="px-3 py-3 align-top">{{ $revision->comment }}</td>
                                        <td class="px-3 py-3 align-top">
                                            <form method="POST" action="{{ route('admin.surveys.supervisor-review.revisions.update', ['survey' => $survey, 'revision' => $revision]) }}" class="space-y-2">
                                                @csrf
                                                @method('PUT')
                                                <textarea name="researcher_response" rows="2" class="block w-80 rounded-md border-slate-300">{{ $revision->researcher_response }}</textarea>
                                                <textarea name="action_taken" rows="2" class="block w-80 rounded-md border-slate-300" placeholder="Action taken">{{ $revision->action_taken }}</textarea>
                                                <input name="revised_version" value="{{ $revision->revised_version }}" placeholder="version/date note" class="block w-80 rounded-md border-slate-300">
                                                <input type="datetime-local" name="revised_at" value="{{ $revision->revised_at?->format('Y-m-d\TH:i') }}" class="block w-80 rounded-md border-slate-300">
                                                <select name="status" class="block w-80 rounded-md border-slate-300">
                                                    @foreach ($revisionStatuses as $value => $label)
                                                        <option value="{{ $value }}" @selected($revision->status === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">Save Response</button>
                                            </form>
                                        </td>
                                        <td class="px-3 py-3 align-top"><span class="rounded-full border px-2 py-1 text-xs font-semibold {{ $badge($revision->status) }}">{{ $revisionStatuses[$revision->status] ?? $revision->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-3 py-5 text-slate-600">No revision matrix rows yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @empty
            <section class="mt-6 rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">No supervisor review rounds yet.</section>
        @endforelse
    </main>
</body>
</html>
