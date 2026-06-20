<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supervisor Instrument Review Report</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-slate-950 antialiased">
    <main class="mx-auto max-w-5xl px-8 py-10">
        <h1 class="text-3xl font-semibold">Supervisor Instrument Review Report</h1>
        <p class="mt-2 text-sm text-slate-600">Generated {{ $generatedAt->format('Y-m-d H:i') }}</p>

        <section class="mt-8">
            <h2 class="text-xl font-semibold">Instrument Metadata</h2>
            <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                <div><dt class="font-semibold">Instrument</dt><dd>{{ $survey->title }}</dd></div>
                <div><dt class="font-semibold">Project</dt><dd>{{ $survey->project?->title ?: 'No project' }}</dd></div>
                <div><dt class="font-semibold">Round</dt><dd>{{ $round->title }}</dd></div>
                <div><dt class="font-semibold">Status</dt><dd>{{ str($round->status)->replace('_', ' ')->title() }}</dd></div>
                <div><dt class="font-semibold">Reviewed Version Timestamp</dt><dd>{{ $round->snapshot_taken_at?->format('Y-m-d H:i') ?: 'Not captured' }}</dd></div>
                <div><dt class="font-semibold">Instrument Changed After Review</dt><dd>{{ $instrumentChanged ? 'Yes' : 'No' }}</dd></div>
            </dl>
        </section>

        <section class="mt-8">
            <h2 class="text-xl font-semibold">Supervisors and Decisions</h2>
            <div class="mt-3 space-y-3">
                @foreach ($round->reviewers as $reviewer)
                    <article class="rounded-lg border border-slate-200 p-4">
                        <p class="font-semibold">{{ $reviewer->supervisor_name }} {{ $reviewer->supervisor_code ? '('.$reviewer->supervisor_code.')' : '' }}</p>
                        <p class="text-sm text-slate-600">{{ str($reviewer->status)->replace('_', ' ')->title() }} · {{ $reviewer->submitted_at?->format('Y-m-d H:i') ?: 'Not submitted' }}</p>
                        <p class="mt-2 text-sm">Decision: {{ \App\Models\SurveySupervisorReviewer::DECISION_LABELS[$reviewer->final_decision] ?? 'Pending' }}</p>
                        @if ($reviewer->final_notes)
                            <p class="mt-1 text-sm">Final notes: {{ $reviewer->final_notes }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-xl font-semibold">Comments</h2>
            <div class="mt-3 space-y-3">
                @forelse ($round->reviewers->flatMap->comments as $comment)
                    <article class="rounded-lg border border-slate-200 p-4 text-sm">
                        <p class="font-semibold">{{ str($comment->comment_type)->title() }} · {{ $comment->target_key ?: $comment->target_label }}</p>
                        <p class="mt-1">{{ $comment->comment }}</p>
                        @if ($comment->suggested_revision)
                            <p class="mt-1 text-slate-600">Suggested revision: {{ $comment->suggested_revision }}</p>
                        @endif
                        <p class="mt-1 text-slate-600">Severity: {{ $comment->severity ?: 'N/A' }} · Decision: {{ $comment->decision ?: 'N/A' }}</p>
                    </article>
                @empty
                    <p class="text-sm text-slate-600">No comments submitted.</p>
                @endforelse
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-xl font-semibold">Revision Matrix</h2>
            <table class="mt-3 min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-3 py-2">Item/Section</th>
                        <th class="px-3 py-2">Supervisor</th>
                        <th class="px-3 py-2">Comment</th>
                        <th class="px-3 py-2">Suggested Revision</th>
                        <th class="px-3 py-2">Researcher Response</th>
                        <th class="px-3 py-2">Action Taken</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($round->revisions as $revision)
                        <tr>
                            <td class="px-3 py-2">{{ $revision->item_label }}</td>
                            <td class="px-3 py-2">{{ $revision->supervisor_code }}</td>
                            <td class="px-3 py-2">{{ $revision->comment }}</td>
                            <td class="px-3 py-2">{{ $revision->suggested_revision }}</td>
                            <td class="px-3 py-2">{{ $revision->researcher_response }}</td>
                            <td class="px-3 py-2">{{ $revision->action_taken }}</td>
                            <td class="px-3 py-2">{{ str($revision->status)->replace('_', ' ')->title() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-5 text-slate-600">No revision matrix rows.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="mt-8">
            <h2 class="text-xl font-semibold">Readiness Recommendation</h2>
            <p class="mt-2 text-sm leading-6">Supervisor review is qualitative pre-validation evidence and remains separate from expert validation scoring. It is not included in Aiken's V or CVI. Proceed to expert validation when required researcher responses and revision actions are complete.</p>
        </section>
    </main>
</body>
</html>
