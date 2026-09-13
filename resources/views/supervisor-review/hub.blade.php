@php
    use App\Models\SurveySupervisorReviewer;
    use App\Models\SurveySupervisorReviewerHub;

    $statusLabel = fn (SurveySupervisorReviewer $reviewer): string => $reviewer->isSubmitted()
        ? 'Selesai'
        : ($reviewer->status === SurveySupervisorReviewer::STATUS_NOT_OPENED ? 'Belum dimulai' : 'Dalam proses');
    $statusClass = fn (SurveySupervisorReviewer $reviewer): string => $reviewer->isSubmitted()
        ? 'bg-emerald-100 text-emerald-800'
        : ($reviewer->status === SurveySupervisorReviewer::STATUS_NOT_OPENED ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-800');
    $buttonLabel = fn (SurveySupervisorReviewer $reviewer): string => $reviewer->isSubmitted()
        ? 'Lihat Hasil'
        : ($reviewer->status === SurveySupervisorReviewer::STATUS_NOT_OPENED ? 'Mulai Review' : 'Lanjutkan');
    $overallLabels = [
        SurveySupervisorReviewerHub::STATUS_NOT_STARTED => 'Belum dimulai',
        SurveySupervisorReviewerHub::STATUS_IN_PROGRESS => 'Dalam proses',
        SurveySupervisorReviewerHub::STATUS_COMPLETED => 'Selesai',
    ];
    $overall = $hub->overallStatus();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reviewer Hub PharmVR - MyRiset</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-950 antialiased">
<main class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-10 lg:px-8">
    <header class="overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-950 via-indigo-900 to-blue-700 text-white shadow-xl shadow-indigo-950/10">
        <div class="p-6 sm:p-9">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-200">Reviewer Hub PharmVR</p>
            <h1 class="mt-3 text-2xl font-semibold sm:text-4xl">Selamat datang, {{ $hub->supervisor_name }}</h1>
            <p class="mt-4 max-w-3xl text-sm leading-7 text-indigo-100 sm:text-base">Satu ruang untuk meninjau tiga instrumen PharmVR v2.0. Setiap instrumen dikirim final secara terpisah agar komentar, keputusan item, usulan redaksi, dan bukti review tetap tercatat dengan jelas.</p>
            <p class="mt-3 max-w-3xl rounded-xl bg-white/10 px-4 py-3 text-sm leading-6 text-indigo-50">Tautan ini khusus untuk Anda. Jangan teruskan kepada orang lain. Hasil setiap instrumen otomatis tersambung ke Laporan Review Pembimbing di MyRiset.</p>
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <span class="rounded-full bg-white/15 px-4 py-2 text-sm font-semibold">Status keseluruhan: {{ $overallLabels[$overall] }}</span>
                <span class="text-sm text-indigo-100">Berlaku sampai {{ $hub->expires_at->translatedFormat('d F Y') }}</span>
            </div>
        </div>
    </header>

    <section class="mt-6 rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm sm:p-7">
        <h2 class="text-lg font-semibold text-blue-950">Dasar Penyusunan Instrumen</h2>
        <p class="mt-2 text-sm leading-6 text-blue-900">Instrumen ini dikembangkan berdasarkan tahap Analysis model ADDIE, proposal penelitian PharmVR, ketentuan CPOB yang berlaku, prinsip pembelajaran pendidikan tinggi berbasis capaian, serta literatur pembelajaran VR dan pengembangan instrumen penelitian.</p>
        <details class="mt-4 rounded-xl border border-blue-200 bg-white p-4">
            <summary class="cursor-pointer text-sm font-bold text-blue-800">Lihat Referensi Utama</summary>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
                <li>PerBPOM No. 7 Tahun 2024 jo. PerBPOM No. 7 Tahun 2025 tentang CPOB</li>
                <li>Branch, 2009 — ADDIE</li>
                <li>Makransky &amp; Petersen, 2021 — CAMIL</li>
                <li>Mishra &amp; Koehler, 2006 — TPACK</li>
                <li>Collins/Brown/Newman — Cognitive Apprenticeship</li>
                <li>Artino et al., 2014 — questionnaire development</li>
                <li>AAPOR Best Practices</li>
            </ul>
        </details>
    </section>
    <section class="mt-6 rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-lg font-semibold">Cara menyelesaikan review</h2>
        <ol class="mt-4 grid gap-3 text-sm leading-6 text-slate-700 md:grid-cols-3">
            <li class="rounded-xl bg-slate-50 p-4"><span class="font-bold text-indigo-700">1.</span> Buka instrumen dan baca informasi awalnya.</li>
            <li class="rounded-xl bg-slate-50 p-4"><span class="font-bold text-indigo-700">2.</span> Beri keputusan dan komentar untuk setiap item.</li>
            <li class="rounded-xl bg-slate-50 p-4"><span class="font-bold text-indigo-700">3.</span> Kirim keputusan final pada tiap instrumen.</li>
        </ol>
        <p class="mt-4 text-sm leading-6 text-slate-600">Anda dapat berhenti setelah menyelesaikan satu instrumen dan kembali melalui tautan yang sama. Status berubah menjadi <strong>Dalam proses</strong>, lalu <strong>Selesai</strong> setelah keputusan final dikirim.</p>
    </section>

    <section class="mt-6 grid gap-4">
        @foreach ($reviewers as $reviewer)
            @php($survey = $reviewer->round->survey)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-lg bg-indigo-950 px-2.5 py-1 text-xs font-bold text-white">{{ str($survey->instrument_code)->before('-') }}</span>
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusClass($reviewer) }}">{{ $statusLabel($reviewer) }}</span>
                        </div>
                        <h2 class="mt-3 break-words text-lg font-semibold sm:text-xl">{{ $survey->title }}</h2>
                        <p class="mt-2 text-sm text-slate-600">Versi {{ $survey->instrument_version }} · {{ data_get($reviewer->round->snapshot_json, 'pages', []) ? count(collect($reviewer->round->snapshot_json['pages'])->flatMap(fn ($page) => $page['questions'] ?? [])) : $survey->questions()->count() }} item</p>
                    </div>
                    <a href="{{ route('supervisor-review.hub.instrument.show', compact('token', 'reviewer')) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-indigo-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">{{ $buttonLabel($reviewer) }}</a>
                </div>
            </article>
        @endforeach
    </section>

    <p class="mt-6 text-center text-xs leading-5 text-slate-500">Status keseluruhan menjadi Selesai setelah ketiga instrumen dikirim final.</p>
</main>
</body>
</html>
