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
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Pembimbing - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">Review Pembimbing Instrumen</p>
                    <h1 class="mt-2 text-3xl font-semibold">{{ $survey->title }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Review sebelum validasi ahli. Pembimbing menilai snapshot instrumen tanpa mengedit pertanyaan secara langsung.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.surveys.builder.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Ruang kerja</a>
                    <a href="{{ route('admin.surveys.validation.index', ['survey' => $survey]) }}" class="rounded-md border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">Validasi ahli</a>
                    <a href="{{ route('admin.surveys.preflight.index', ['survey' => $survey]) }}" class="rounded-md border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-900 shadow-sm hover:bg-amber-50">Kesiapan</a>
                </div>
            </div>

            @if ($survey->instrument_identifier)
                <div class="mt-6 grid gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div><p class="text-xs font-semibold uppercase text-emerald-700">Versi</p><p class="mt-1 font-semibold">{{ $survey->instrument_identifier }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-emerald-700">Status</p><p class="mt-1 font-semibold">{{ $workflow['label'] }}</p></div>
                    <div><p class="text-xs font-semibold uppercase text-emerald-700">Reviewer</p><p class="mt-1 font-semibold">{{ $rounds->flatMap->reviewers->sortByDesc('created_at')->first()?->supervisor_name ?: 'Belum ditugaskan' }}</p></div>
                    <div class="sm:col-span-2"><p class="text-xs font-semibold uppercase text-emerald-700">Tindakan berikutnya</p><p class="mt-1 font-semibold">{{ $workflow['next_action'] }}</p></div>
                    <div class="sm:col-span-2 lg:col-span-5"><div class="h-2 overflow-hidden rounded-full bg-white"><div class="h-full rounded-full bg-emerald-600" style="width: {{ $workflow['percent'] }}%"></div></div></div>
                </div>
            @endif

            @if (session('generated_supervisor_review_url'))
                <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-950">Supervisor review link generated. Raw token is shown once.</p>
                    <input readonly value="{{ session('generated_supervisor_review_url') }}" class="mt-3 block w-full rounded-md border border-amber-300 bg-white px-3 py-2 font-mono text-xs">
                </div>
            @endif
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Buat Putaran Review</h2>
            <form method="POST" action="{{ route('admin.surveys.supervisor-review.rounds.store', ['survey' => $survey]) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Nama putaran</span>
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
                    <span class="text-sm font-medium text-slate-700">Batas waktu</span>
                    <input type="date" name="due_date" class="mt-1 block w-full rounded-md border-slate-300">
                </label>
                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-slate-700">Tujuan review</span>
                    <textarea name="purpose" rows="3" class="mt-1 block w-full rounded-md border-slate-300">Pre-validation supervisor review before expert validation.</textarea>
                </label>
                <div class="md:col-span-2">
                    <button class="rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-600">Buat putaran</button>
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
                                        <div class="flex flex-wrap gap-2">
                        <a target="_blank" href="{{ route('admin.surveys.supervisor-review.report', ['survey' => $survey, 'round' => $round]) }}" class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm hover:bg-blue-50">Preview laporan</a>
                        @if ($round->finalized_at && $survey->workflow_state === \App\Modules\Surveys\Services\SurveyInstrumentWorkflowService::SUPERVISOR_REVISION_REQUIRED)
                            <form method="POST" action="{{ route('admin.surveys.supervisor-review.revision.create', ['survey' => $survey, 'round' => $round]) }}">@csrf<button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Buat versi revisi</button></form>
                        @endif
                    </div>
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
                        <h3 class="font-semibold">Tambahkan pembimbing</h3>
                        <form method="POST" action="{{ route('admin.surveys.supervisor-review.reviewers.store', ['survey' => $survey, 'round' => $round]) }}" class="mt-3 space-y-3">
                            @csrf
                            <input name="supervisor_name" placeholder="Nama pembimbing" required class="block w-full rounded-md border-slate-300">
                            <input name="supervisor_email" placeholder="email (opsional)" class="block w-full rounded-md border-slate-300">
                            <input name="supervisor_code" placeholder="SPV-1" class="block w-full rounded-md border-slate-300">
                            <input name="role" placeholder="Promotor / Co-promotor" class="block w-full rounded-md border-slate-300">
                            <input type="datetime-local" name="expires_at" class="block w-full rounded-md border-slate-300">
                            <button class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Tambahkan pembimbing</button>
                        </form>
                    </div>

                    <div class="lg:col-span-2">
                        <h3 class="font-semibold">Progress review</h3>
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
                                                <button class="rounded-md border border-indigo-300 bg-white px-3 py-2 text-xs font-semibold text-indigo-800 shadow-sm hover:bg-indigo-50">Buat tautan</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.surveys.supervisor-review.reviewers.revoke-link', ['survey' => $survey, 'reviewer' => $reviewer]) }}">
                                                @csrf
                                                <button class="rounded-md border border-red-300 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-50">Cabut</button>
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
                                <p class="p-4 text-sm text-slate-600">Belum ada pembimbing yang ditugaskan.</p>
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
            <section class="mt-6 rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">Belum ada putaran review pembimbing.</section>
        @endforelse
    </main>
</body>
</html>
