@php
    $format = fn ($value): string => $value === null ? 'N/A' : number_format((float) $value, 2);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Survey Readability Test - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section data-ui="myriset-page-header" class="mb-8 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Admin</p>
                    <h1 class="mt-2 text-3xl font-semibold">Readability Test</h1>
                    <p class="mt-2 text-sm text-slate-600">{{ $survey->title }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $survey->project?->title ?? 'No project' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.surveys.builder.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Builder</a>
                    <a href="{{ route('admin.surveys.validation.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Validation</a>
                    <a href="{{ url('/admin/surveys') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back to Surveys</a>
                </div>
            </div>
        </section>

        @if (session('generated_readability_url'))
            <section class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950 shadow-sm">
                <p class="font-semibold">Copy this readability URL now. It is shown only once.</p>
                <p class="mt-1">The raw token is not stored and cannot be recovered later. Regenerate a link if it is lost.</p>
                <input readonly value="{{ session('generated_readability_url') }}" class="mt-3 block w-full rounded-md border border-amber-300 bg-white px-3 py-2 font-mono text-xs text-slate-900 shadow-sm">
            </section>
        @endif

        @if (session('status'))
            <section class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
                {{ str_replace('-', ' ', session('status')) }}
            </section>
        @endif

        @if ($errors->any())
            <section class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Please review the readability form.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Questions</p>
                <p class="mt-2 text-2xl font-semibold">{{ $survey->questions->where('type', '!=', \App\Models\SurveyQuestion::TYPE_HIDDEN)->count() }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rounds</p>
                <p class="mt-2 text-2xl font-semibold">{{ $rounds->count() }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Participants</p>
                <p class="mt-2 text-2xl font-semibold">{{ $rounds->sum(fn ($round) => $round->participants->count()) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted</p>
                <p class="mt-2 text-2xl font-semibold">{{ $rounds->sum(fn ($round) => $round->participants->where('status', \App\Models\SurveyReadabilityParticipant::STATUS_SUBMITTED)->count()) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Average</p>
                <p class="mt-2 text-2xl font-semibold">{{ $result ? $format($result['summary']['average_readability_score']) : 'N/A' }}</p>
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Create Readability Round</h2>
            <form method="POST" action="{{ route('admin.surveys.readability.rounds.store', ['survey' => $survey]) }}" class="mt-5 grid gap-4 lg:grid-cols-3">
                @csrf
                <div>
                    <label for="round_title" class="block text-sm font-medium text-slate-700">Title</label>
                    <input id="round_title" name="title" required value="{{ old('title', 'Readability Test Round') }}" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </div>
                <div>
                    <label for="round_status" class="block text-sm font-medium text-slate-700">Status</label>
                    <select id="round_status" name="status" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                        @foreach ($roundStatuses as $value => $label)
                            <option value="{{ $value }}" @selected($value === \App\Models\SurveyReadabilityRound::STATUS_OPEN)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="target_participants" class="block text-sm font-medium text-slate-700">Target Participants</label>
                    <input id="target_participants" name="target_participants" type="number" min="1" max="100" value="{{ old('target_participants', 10) }}" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </div>
                <div>
                    <label for="starts_at" class="block text-sm font-medium text-slate-700">Starts At</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </div>
                <div>
                    <label for="ends_at" class="block text-sm font-medium text-slate-700">Ends At</label>
                    <input id="ends_at" name="ends_at" type="datetime-local" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </div>
                <div class="lg:col-span-3">
                    <label for="instructions" class="block text-sm font-medium text-slate-700">Instructions</label>
                    <textarea id="instructions" name="instructions" rows="3" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">Mohon meninjau apakah instrumen mudah dipahami, tidak ambigu, tidak terlalu panjang, serta sesuai untuk responden mahasiswa/dosen pada tahap Analysis ADDIE PharmVR.</textarea>
                </div>
                <button type="submit" class="lg:col-span-3 rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Create Readability Round</button>
            </form>
        </section>

        <section class="space-y-6">
            @forelse ($rounds as $round)
                @php
                    $submittedCount = $round->participants->where('status', \App\Models\SurveyReadabilityParticipant::STATUS_SUBMITTED)->count();
                    $pendingCount = $round->participants->whereIn('status', [\App\Models\SurveyReadabilityParticipant::STATUS_PENDING, \App\Models\SurveyReadabilityParticipant::STATUS_OPENED])->count();
                    $revokedCount = $round->participants->where('status', \App\Models\SurveyReadabilityParticipant::STATUS_REVOKED)->count();
                @endphp

                <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-semibold">{{ $round->title }}</h2>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $roundStatuses[$round->status] ?? $round->status }}</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600">Target {{ $round->target_participants ?? 10 }} pilot respondents. {{ $round->instructions ?: 'No custom instructions.' }}</p>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center text-sm">
                            <div class="rounded-md bg-emerald-50 px-3 py-2 text-emerald-900">
                                <p class="font-semibold">{{ $submittedCount }}</p>
                                <p class="text-xs">Submitted</p>
                            </div>
                            <div class="rounded-md bg-amber-50 px-3 py-2 text-amber-900">
                                <p class="font-semibold">{{ $pendingCount }}</p>
                                <p class="text-xs">Pending</p>
                            </div>
                            <div class="rounded-md bg-slate-100 px-3 py-2 text-slate-700">
                                <p class="font-semibold">{{ $revokedCount }}</p>
                                <p class="text-xs">Revoked</p>
                            </div>
                        </div>
                    </div>

                    <details class="mt-5 rounded-md border border-slate-200 bg-slate-50">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-700">Edit readability round</summary>
                        <form method="POST" action="{{ route('admin.surveys.readability.rounds.update', ['survey' => $survey, 'round' => $round]) }}" class="grid gap-3 border-t border-slate-200 p-4 lg:grid-cols-3">
                            @csrf
                            @method('PUT')
                            <input name="title" value="{{ $round->title }}" required class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            <select name="status" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                @foreach ($roundStatuses as $value => $label)
                                    <option value="{{ $value }}" @selected($round->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input name="target_participants" type="number" min="1" max="100" value="{{ $round->target_participants }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            <input name="starts_at" type="datetime-local" value="{{ $round->starts_at?->format('Y-m-d\TH:i') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            <input name="ends_at" type="datetime-local" value="{{ $round->ends_at?->format('Y-m-d\TH:i') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            <textarea name="instructions" rows="3" class="lg:col-span-3 rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ $round->instructions }}</textarea>
                            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Save Round</button>
                        </form>
                    </details>

                    <div class="mt-5 rounded-md border border-slate-200 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h3 class="font-semibold">Add Pilot Participant</h3>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.surveys.readability.results.show', ['survey' => $survey, 'round' => $round]) }}" class="rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">View Results</a>
                                <a href="{{ route('admin.surveys.readability.report', ['survey' => $survey, 'round' => $round]) }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Printable Report</a>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.surveys.readability.participants.store', ['survey' => $survey, 'round' => $round]) }}" class="mt-4 grid gap-3 lg:grid-cols-5">
                            @csrf
                            <input name="participant_name" placeholder="Name optional" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            <input name="participant_email" type="email" placeholder="Email optional" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            <select name="participant_type" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                <option value="">Participant type</option>
                                @foreach ($participantTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input name="institution" placeholder="Institution optional" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Add Participant</button>
                        </form>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="py-3 pr-4">Participant</th>
                                    <th class="py-3 pr-4">Type</th>
                                    <th class="py-3 pr-4">Status</th>
                                    <th class="py-3 pr-4">Dates</th>
                                    <th class="py-3 pr-4">Result</th>
                                    <th class="py-3 pr-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($round->participants as $participant)
                                    <tr>
                                        <td class="py-3 pr-4">
                                            <p class="font-medium">{{ $participant->participant_name ?: 'Anonymous pilot' }}</p>
                                            <p class="text-xs text-slate-500">{{ $participant->institution ?: 'No institution recorded' }}</p>
                                        </td>
                                        <td class="py-3 pr-4">{{ $participantTypes[$participant->participant_type] ?? ($participant->participant_type ?: 'Not set') }}</td>
                                        <td class="py-3 pr-4">{{ $participantStatuses[$participant->status] ?? $participant->status }}</td>
                                        <td class="py-3 pr-4">
                                            <p>Opened: {{ $participant->opened_at?->format('Y-m-d H:i') ?? 'Not yet' }}</p>
                                            <p>Submitted: {{ $participant->submitted_at?->format('Y-m-d H:i') ?? 'Not yet' }}</p>
                                        </td>
                                        <td class="py-3 pr-4">
                                            @if ($participant->response)
                                                <p class="font-semibold">{{ \App\Models\SurveyReadabilityResponse::DECISION_LABELS[$participant->response->final_decision] ?? $participant->response->final_decision }}</p>
                                                <p class="text-xs text-slate-600">Clarity {{ $participant->response->overall_clarity_score }} | Terms {{ $participant->response->terminology_clarity_score }} | Length {{ $participant->response->overall_length_score }}</p>
                                            @else
                                                <span class="text-slate-500">No feedback yet</span>
                                            @endif
                                        </td>
                                        <td class="py-3 pr-4">
                                            <div class="flex flex-col gap-2">
                                                <form method="POST" action="{{ route('admin.surveys.readability.participants.generate-link', ['survey' => $survey, 'participant' => $participant]) }}">
                                                    @csrf
                                                    <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-slate-800">
                                                        {{ $participant->token_hash ? 'Regenerate Link' : 'Generate Link' }}
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.surveys.readability.participants.revoke-link', ['survey' => $survey, 'participant' => $participant]) }}">
                                                    @csrf
                                                    <button type="submit" onclick="return confirm('Revoke this readability link?')" @disabled($participant->isSubmitted() || $participant->isRevoked()) class="w-full rounded-md border border-red-300 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-50 disabled:border-slate-200 disabled:text-slate-400">Revoke Link</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-sm text-slate-500">No pilot participants assigned to this readability round.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            @empty
                <section class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm">
                    <h2 class="text-xl font-semibold">No readability rounds yet</h2>
                    <p class="mt-2 text-sm text-slate-600">Create a readability round to invite 5-10 pilot respondents before broader survey distribution.</p>
                </section>
            @endforelse
        </section>

        @if ($result)
            <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Revision Matrix</h2>
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="py-3 pr-4">Question</th>
                                <th class="py-3 pr-4">Issue</th>
                                <th class="py-3 pr-4">Researcher Action</th>
                                <th class="py-3 pr-4">Status</th>
                                <th class="py-3 pr-4">Note</th>
                                <th class="py-3 pr-4">Save</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($result['revision_matrix'] as $revision)
                                <tr>
                                    <td class="py-3 pr-4">{{ $revision['question_number'] ? '#'.$revision['question_number'].' ' : '' }}{{ $revision['question_text'] ?: 'Overall instrument' }}</td>
                                    <td class="py-3 pr-4">{{ $revision['issue_summary'] }}</td>
                                    <td colspan="4" class="py-3 pr-4">
                                        <form method="POST" action="{{ route('admin.surveys.readability.revisions.update', ['survey' => $survey, 'revision' => $revision['id']]) }}" class="grid gap-3 lg:grid-cols-[1.2fr_0.8fr_1.2fr_auto]">
                                            @csrf
                                            @method('PUT')
                                            <textarea name="revision_action" rows="2" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm" placeholder="Suggested revision or researcher action">{{ $revision['revision_action'] }}</textarea>
                                            <select name="status" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                                @foreach ($revisionStatuses as $value => $label)
                                                    <option value="{{ $value }}" @selected($revision['status'] === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <textarea name="researcher_note" rows="2" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm" placeholder="Researcher note">{{ $revision['researcher_note'] }}</textarea>
                                            <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-slate-800">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-sm text-slate-500">No readability revision suggestions submitted yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </main>
</body>
</html>
