@php
    use App\Models\SurveySupervisorReviewComment;
    use App\Modules\Surveys\Services\SurveyInstrumentWorkflowService;

    $surveySnapshot = $snapshot['survey'] ?? [];
    $pages = collect($snapshot['pages'] ?? []);
    $withoutPage = collect($snapshot['questions_without_page'] ?? []);
    $workflowState = $surveySnapshot['workflow_state'] ?? $survey->workflow_state ?? SurveyInstrumentWorkflowService::UNDER_SUPERVISOR_REVIEW;
    $workflowLabel = SurveyInstrumentWorkflowService::LABELS[$workflowState] ?? str($workflowState)->replace('_', ' ')->title();
    $choiceLabels = function (array $question): string {
        $options = $question['options'] ?? [];
        $settings = $question['settings'] ?? [];
        $values = $options['choices'] ?? $options['options'] ?? $options['scale'] ?? $settings['scale'] ?? [];
        $labels = $settings['scale_labels'] ?? [];
        return collect(is_array($values) ? $values : [])->map(function ($value) use ($labels): string {
            $raw = is_array($value) ? (string) ($value['value'] ?? $value['label'] ?? '') : (string) $value;
            return isset($labels[$raw]) ? $raw.' — '.$labels[$raw] : $raw;
        })->filter()->join(' · ');
    };
    $allQuestions = $pages->flatMap(fn (array $page): array => $page['questions'] ?? [])->merge($withoutPage);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Pembimbing Instrumen - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
<main class="mx-auto max-w-5xl px-3 py-5 sm:px-6 sm:py-8 lg:px-8">
    @if (filled($hubUrl ?? null))
        <a href="{{ $hubUrl }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-indigo-700 hover:text-indigo-900">← Kembali ke Reviewer Hub</a>
    @endif
    <header class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-indigo-950 via-indigo-900 to-blue-800 p-5 text-white sm:p-7">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-200">Review Pembimbing Instrumen</p>
            <h1 class="mt-2 break-words text-2xl font-semibold sm:text-3xl">{{ $surveySnapshot['title'] ?? $survey->title }}</h1>
            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><p class="text-indigo-200">Versi</p><p class="font-semibold">v{{ $surveySnapshot['instrument_version'] ?? $survey->instrument_version ?? '—' }}</p></div>
                <div><p class="text-indigo-200">Status</p><p class="font-semibold">{{ $workflowLabel }}</p></div>
                <div><p class="text-indigo-200">Reviewer</p><p class="font-semibold">{{ $reviewer->supervisor_name }}</p></div>
                <div><p class="text-indigo-200">Tanggal versi</p><p class="font-semibold">{{ $round->snapshot_taken_at?->format('d M Y, H:i') ?: '—' }}</p></div>
            </div>
        </div>
        <div class="grid gap-4 p-5 sm:p-7 md:grid-cols-[1fr_auto] md:items-center">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Tindakan berikutnya</p>
                <p class="mt-1 text-lg font-semibold">Tinjau seluruh item, lalu kirim keputusan final</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">Anda menilai salinan terkunci versi ini. Pertanyaan tidak dapat diedit dari halaman review.</p>
            </div>
            <div class="min-w-40">
                <div class="flex justify-between text-xs font-semibold text-slate-500"><span>Progres</span><span id="review-progress">0/{{ $allQuestions->count() }}</span></div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"><div id="review-progress-bar" class="h-full w-0 rounded-full bg-indigo-600 transition-all"></div></div>
            </div>
        </div>
    </header>

    <section class="mt-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Info review</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">Anda meninjau versi {{ $surveySnapshot['instrument_version'] ?? $survey->instrument_version ?? '—' }} yang telah dikunci saat ronde review dibuka. Isi pertanyaan tidak dapat diubah dari halaman ini.</p>
    </section>

    @if ($errors->any())
        <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert">
            <p class="font-semibold">Review belum dapat dikirim.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $formAction ?? route('supervisor-review.survey.store', ['token' => $token]) }}" class="mt-5 space-y-5" data-review-form>
        @csrf

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-semibold">Informasi instrumen</h2>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $surveySnapshot['intro_text'] ?? '' }}</p>
            <div class="mt-4 grid gap-3 text-sm md:grid-cols-3">
                <div class="rounded-lg bg-slate-50 p-3"><p class="font-semibold">Kerahasiaan</p><p class="mt-1 text-slate-600">{{ $surveySnapshot['privacy_statement'] ?? '—' }}</p></div>
                <div class="rounded-lg bg-slate-50 p-3"><p class="font-semibold">Instruksi responden</p><p class="mt-1 text-slate-600">{{ $surveySnapshot['respondent_instruction'] ?? '—' }}</p></div>
                <div class="rounded-lg bg-slate-50 p-3"><p class="font-semibold">Persetujuan</p><p class="mt-1 text-slate-600">{{ $surveySnapshot['consent_text'] ?? '—' }}</p></div>
            </div>
        </section>

        @foreach ($pages as $page)
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <p class="text-xs font-bold uppercase tracking-wide text-indigo-700">Bagian {{ $loop->iteration }}</p>
                <h2 class="mt-1 text-xl font-semibold">{{ $page['title'] }}</h2>
                @if (filled($page['description'] ?? null))<p class="mt-2 text-sm text-slate-600">{{ $page['description'] }}</p>@endif
                <div class="mt-5 space-y-4">
                    @foreach (collect($page['questions'] ?? [])->sortBy('sort_order') as $question)
                        @include('supervisor-review.partials.item-card', ['question' => $question, 'choiceLabels' => $choiceLabels, 'itemDecisions' => $itemDecisions, 'severities' => $severities])
                    @endforeach
                </div>
            </section>
        @endforeach

        @if ($withoutPage->isNotEmpty())
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <h2 class="text-xl font-semibold">Item tanpa bagian</h2>
                <div class="mt-5 space-y-4">@foreach ($withoutPage as $question) @include('supervisor-review.partials.item-card', ['question' => $question, 'choiceLabels' => $choiceLabels, 'itemDecisions' => $itemDecisions, 'severities' => $severities]) @endforeach</div>
            </section>
        @endif

        <section class="rounded-xl border border-indigo-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-xl font-semibold">Keputusan akhir</h2>
            <label class="mt-4 block"><span class="text-sm font-semibold">Komentar umum</span><textarea name="final_notes" rows="4" class="mt-2 block w-full rounded-lg border-slate-300" placeholder="Ringkas pertimbangan dan arahan pembimbing">{{ old('final_notes') }}</textarea></label>
            <fieldset class="mt-5"><legend class="text-sm font-semibold">Keputusan final</legend><div class="mt-2 grid gap-3 sm:grid-cols-2">@foreach ($decisions as $value => $label)<label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-4 hover:border-indigo-300"><input type="radio" name="final_decision" value="{{ $value }}" required @checked(old('final_decision') === $value)><span class="font-semibold">{{ $label }}</span></label>@endforeach</div></fieldset>
            <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">Pengiriman final bersifat eksplisit dan mengunci bukti review untuk versi ini.</div>
            <button type="submit" class="mt-5 w-full rounded-lg bg-indigo-700 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-indigo-600 sm:w-auto">Kirim Review Final</button>
        </section>
    </form>
</main>
<script>
(() => {
    const selects = [...document.querySelectorAll('[data-item-decision]')];
    const label = document.getElementById('review-progress');
    const bar = document.getElementById('review-progress-bar');
    const update = () => {
        const filled = selects.filter((select) => select.value !== '').length;
        label.textContent = `${filled}/${selects.length}`;
        bar.style.width = `${selects.length ? (filled / selects.length) * 100 : 100}%`;
    };
    selects.forEach((select) => select.addEventListener('change', update));
    update();
})();
</script>
</body>
</html>
