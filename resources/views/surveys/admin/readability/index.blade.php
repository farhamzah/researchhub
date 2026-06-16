@php
    $format = fn ($value): string => $value === null ? 'N/A' : number_format((float) $value, 2);
    $hasRounds = $rounds->isNotEmpty();
    $participantCount = $rounds->sum(fn ($round) => $round->participants->count());
    $submittedCount = $rounds->sum(fn ($round) => $round->participants->where('status', \App\Models\SurveyReadabilityParticipant::STATUS_SUBMITTED)->count());
    $revisionCount = $result ? count($result['revision_matrix']) : 0;
    $workflowSteps = [
        ['label' => 'Step 1', 'title' => 'Create Round', 'description' => 'Buat putaran uji keterbacaan.', 'done' => $hasRounds],
        ['label' => 'Step 2', 'title' => 'Add Pilot Participants', 'description' => 'Tambahkan 5-10 responden kecil.', 'done' => $participantCount > 0],
        ['label' => 'Step 3', 'title' => 'Share Readability Link', 'description' => 'Generate dan bagikan link aman.', 'done' => $rounds->contains(fn ($round) => $round->participants->contains(fn ($participant) => filled($participant->token_hash)))],
        ['label' => 'Step 4', 'title' => 'Review Results', 'description' => 'Baca hasil setelah ada submit.', 'done' => $submittedCount > 0],
        ['label' => 'Step 5', 'title' => 'Finalize Revision Matrix', 'description' => 'Tindak lanjuti isu keterbacaan.', 'done' => $revisionCount > 0],
    ];
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
                    <h1 class="mt-2 text-3xl font-semibold">Uji Keterbacaan</h1>
                    <p class="mt-2 text-sm text-slate-600">{{ $survey->title }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $survey->project?->title ?? 'No project' }}</p>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Uji keterbacaan membantu memastikan instrumen mudah dipahami sebelum disebarkan. Respons uji keterbacaan tidak dihitung sebagai respons survei utama.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.surveys.builder.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Builder</a>
                    <a href="{{ route('admin.surveys.validation.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Validation</a>
                    <a href="{{ route('admin.surveys.analysis.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Analysis Dashboard</a>
                    <a href="{{ route('admin.surveys.distribution.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Distribution Center</a>
                    <a href="{{ url('/admin/surveys') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back to Surveys</a>
                </div>
            </div>
        </section>

        @if (session('generated_readability_url'))
            <section class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950 shadow-sm">
                <p class="font-semibold">Link hanya ditampilkan saat dibuat atau diregenerasi.</p>
                <p class="mt-1">Simpan/copy link ini sebelum meninggalkan halaman. Raw token tidak disimpan dan tidak dapat dilihat kembali.</p>
                <div class="mt-3 flex flex-col gap-2 lg:flex-row">
                    <input id="generated_readability_url" readonly value="{{ session('generated_readability_url') }}" class="block w-full rounded-md border border-amber-300 bg-white px-3 py-2 font-mono text-xs text-slate-900 shadow-sm">
                    <button type="button" onclick="navigator.clipboard?.writeText(document.getElementById('generated_readability_url').value); this.textContent = 'Copied';" class="rounded-md bg-amber-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-800">Copy Link</button>
                    <a href="{{ session('generated_readability_url') }}" target="_blank" class="rounded-md border border-amber-300 bg-white px-4 py-2 text-center text-sm font-semibold text-amber-900 shadow-sm hover:bg-amber-100">Open Link</a>
                </div>
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

        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Alur Uji Keterbacaan</p>
                    <h2 class="mt-1 text-lg font-semibold">Next workflow steps</h2>
                </div>
                <p class="max-w-2xl text-sm text-slate-600">Mulai dari putaran, tambah responden pilot, bagikan link, lalu tindak lanjuti matriks revisi.</p>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-5">
                @foreach ($workflowSteps as $step)
                    <div class="rounded-lg border p-4 {{ $step['done'] ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50' }}">
                        <p class="text-xs font-semibold uppercase tracking-wide {{ $step['done'] ? 'text-emerald-700' : 'text-slate-500' }}">{{ $step['label'] }}</p>
                        <p class="mt-1 font-semibold text-slate-950">{{ $step['title'] }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-600">{{ $step['description'] }}</p>
                        <p class="mt-2 text-xs font-semibold {{ $step['done'] ? 'text-emerald-700' : 'text-slate-500' }}">{{ $step['done'] ? 'Completed / available' : 'Next' }}</p>
                    </div>
                @endforeach
            </div>
        </section>

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

        @if ($hasRounds)
            <details class="mb-6 rounded-lg border border-slate-200 bg-white shadow-sm">
                <summary class="cursor-pointer px-6 py-4 text-sm font-semibold text-slate-800">Create another readability round</summary>
                <div class="border-t border-slate-200 p-6">
        @else
            <section class="mb-6 rounded-lg border border-emerald-200 bg-white p-6 shadow-sm">
        @endif
            <h2 class="text-xl font-semibold">Buat Putaran Uji Keterbacaan</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Uji keterbacaan ini bertujuan menilai apakah instrumen mudah dipahami, tidak ambigu, tidak terlalu panjang, pilihan jawaban jelas, serta sesuai untuk responden pada tahap Analysis ADDIE PharmVR. Respons uji keterbacaan tidak dihitung sebagai respons survei utama.</p>
            <form method="POST" action="{{ route('admin.surveys.readability.rounds.store', ['survey' => $survey]) }}" class="mt-5 grid gap-4 lg:grid-cols-3">
                @csrf
                <div>
                    <label for="round_title" class="block text-sm font-medium text-slate-700">Judul</label>
                    <input id="round_title" name="title" required value="{{ old('title', 'Putaran Uji Keterbacaan') }}" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
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
                    <label for="target_participants" class="block text-sm font-medium text-slate-700">Target Responden</label>
                    <input id="target_participants" name="target_participants" type="number" min="1" max="100" value="{{ old('target_participants', 10) }}" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </div>
                <div>
                    <label for="starts_at" class="block text-sm font-medium text-slate-700">Mulai</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </div>
                <div>
                    <label for="ends_at" class="block text-sm font-medium text-slate-700">Selesai</label>
                    <input id="ends_at" name="ends_at" type="datetime-local" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </div>
                <div class="lg:col-span-3">
                    <label for="instructions" class="block text-sm font-medium text-slate-700">Instruksi</label>
                    <textarea id="instructions" name="instructions" rows="3" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">Uji keterbacaan ini bertujuan menilai apakah instrumen mudah dipahami, tidak ambigu, tidak terlalu panjang, pilihan jawaban jelas, serta sesuai untuk responden pada tahap Analysis ADDIE PharmVR. Respons uji keterbacaan tidak dihitung sebagai respons survei utama.</textarea>
                </div>
                <button type="submit" class="lg:col-span-3 rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Buat Putaran</button>
            </form>
        @if ($hasRounds)
                </div>
            </details>
        @else
            </section>
        @endif

        <section class="space-y-6">
            @forelse ($rounds as $round)
                @php
                    $submittedCount = $round->participants->where('status', \App\Models\SurveyReadabilityParticipant::STATUS_SUBMITTED)->count();
                    $pendingCount = $round->participants->whereIn('status', [\App\Models\SurveyReadabilityParticipant::STATUS_PENDING, \App\Models\SurveyReadabilityParticipant::STATUS_OPENED])->count();
                    $revokedCount = $round->participants->where('status', \App\Models\SurveyReadabilityParticipant::STATUS_REVOKED)->count();
                    $roundAverage = $result && $result['round']->is($round) ? $result['summary']['average_readability_score'] : null;
                @endphp

                <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-semibold">{{ $round->title }}</h2>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $roundStatuses[$round->status] ?? $round->status }}</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600">Target {{ $round->target_participants ?? 10 }} pilot respondents. {{ $round->instructions ?: 'No custom instructions.' }}</p>
                            <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Progress {{ $submittedCount }} / {{ $round->target_participants ?? 10 }} submitted</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="{{ route('admin.surveys.readability.results.show', ['survey' => $survey, 'round' => $round]) }}" class="rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">Lihat Hasil</a>
                                <a href="{{ route('admin.surveys.readability.report', ['survey' => $survey, 'round' => $round]) }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Laporan Cetak</a>
                                <a href="#edit-readability-round-{{ $round->id }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Edit Round</a>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-center text-sm sm:grid-cols-4">
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
                            <div class="rounded-md bg-blue-50 px-3 py-2 text-blue-900">
                                <p class="font-semibold">{{ $roundAverage === null ? 'N/A' : $format($roundAverage) }}</p>
                                <p class="text-xs">Average</p>
                            </div>
                        </div>
                    </div>

                    <details id="edit-readability-round-{{ $round->id }}" class="mt-5 rounded-md border border-slate-200 bg-slate-50">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-700">Edit Round</summary>
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
                            <div>
                                <h3 class="font-semibold">Tambah Responden Pilot</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-600">Tambahkan 5-10 responden kecil untuk menilai keterbacaan instrumen. Respons uji keterbacaan tidak dihitung sebagai respons survei utama.</p>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.surveys.readability.participants.store', ['survey' => $survey, 'round' => $round]) }}" class="mt-4 grid gap-3 lg:grid-cols-5">
                            @csrf
                            <label class="block">
                                <span class="text-xs font-semibold text-slate-600">Nama</span>
                                <input name="participant_name" placeholder="Nama optional" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold text-slate-600">Email</span>
                                <input name="participant_email" type="email" placeholder="Email optional" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold text-slate-600">Jenis Responden</span>
                                <select name="participant_type" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                    <option value="">Participant type</option>
                                    @foreach ($participantTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold text-slate-600">Institusi</span>
                                <input name="institution" placeholder="Institusi optional" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            </label>
                            <button type="submit" class="self-end rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Tambah Responden</button>
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
                                                @if (session('generated_readability_participant_id') === $participant->getKey() && session('generated_readability_url'))
                                                    <input id="generated_readability_url_{{ $participant->id }}" type="hidden" value="{{ session('generated_readability_url') }}">
                                                    <button type="button" onclick="navigator.clipboard?.writeText(document.getElementById('generated_readability_url_{{ $participant->id }}').value); this.textContent = 'Copied';" class="w-full rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900 shadow-sm hover:bg-amber-100">Copy Link</button>
                                                    <a href="{{ session('generated_readability_url') }}" target="_blank" class="w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-center text-xs font-semibold text-amber-900 shadow-sm hover:bg-amber-50">Open Link</a>
                                                @endif
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
                                        <td colspan="6" class="py-6 text-center text-sm text-slate-500">No pilot participants have been added yet. Add 5-10 participants to start the readability test.</td>
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
                <h2 class="text-xl font-semibold">Matriks Revisi</h2>
                <div class="mt-5 overflow-x-auto">
                    @if (count($result['revision_matrix']) > 0)
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
                                @foreach ($result['revision_matrix'] as $revision)
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
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-600">No readability issues have been submitted yet. The revision matrix will appear after participants submit feedback.</div>
                    @endif
                </div>
            </section>
        @else
            <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Matriks Revisi</h2>
                <div class="mt-5 rounded-md border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-600">No readability issues have been submitted yet. The revision matrix will appear after participants submit feedback.</div>
            </section>
        @endif
    </main>
</body>
</html>
