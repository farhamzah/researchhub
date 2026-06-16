@extends('layouts.public-review')

@php
    $statusLabel = 'Aktif';
    $criteria = [
        'Content relevance / Relevansi' => 'Kesesuaian butir dengan kebutuhan analisis dan indikator penelitian.',
        'Language clarity / Kejelasan' => 'Kejelasan bahasa, instruksi, dan redaksi butir.',
        'Construct alignment / Kesesuaian konstruk' => 'Keselarasan butir dengan konstruk yang hendak diukur.',
        'Measurability' => 'Kemampuan butir menghasilkan jawaban yang dapat dianalisis.',
        'Feasibility of use' => 'Kelayakan butir digunakan oleh responden sasaran.',
        'Ethical/privacy suitability / Kebahasaan dan etik' => 'Kesesuaian butir dengan prinsip etik, privasi responden, dan bahasa akademik.',
    ];
    $scaleDescription = match (true) {
        $round->rating_scale_min === 1 && $round->rating_scale_max === 5 => ['1 = Not feasible / not relevant', '2 = Less feasible', '3 = Fairly feasible', '4 = Feasible', '5 = Very feasible'],
        $round->rating_scale_min === 1 && $round->rating_scale_max === 4 => ['1 = Tidak sesuai', '2 = Kurang sesuai', '3 = Sesuai', '4 = Sangat sesuai'],
        default => collect(range($round->rating_scale_min, $round->rating_scale_max))
            ->map(fn (int $score): string => $score.' = Skor '.$score)
            ->all(),
    };
@endphp

@section('title', $round->title.' - MyRiset Validation')

@section('content')
    <section data-ui="myriset-page-header" class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Expert Validation</p>
                <h1 class="mt-2 text-3xl font-semibold">Validasi Ahli Instrumen</h1>
                <p class="mt-3 text-lg font-semibold text-slate-800">{{ $survey->title }}</p>
                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $project?->title ?? 'Research project' }}</p>
            </div>
            <x-myriset.status-badge status="active" :label="'Status: '.$statusLabel" />
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Putaran Validasi</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">{{ $round->title }}</p>
            </div>
            <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Validator</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">{{ $validator->name }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $assignment->role ?: 'Expert validator' }}</p>
            </div>
            <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah Butir</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">{{ $questions->count() }} items need scoring</p>
            </div>
            <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Batas Akses</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">{{ $assignment->expires_at?->format('Y-m-d H:i') ?? 'Tidak ada batas khusus' }}</p>
            </div>
        </div>
    </section>

    <section class="mb-6 grid gap-4 lg:grid-cols-[0.58fr_0.42fr]">
        <div data-ui="myriset-section-card" class="rounded-lg border border-blue-200 bg-blue-50 p-5 text-sm leading-6 text-blue-950">
            <h2 class="text-lg font-semibold">Petunjuk validasi</h2>
            <p class="mt-2">Bapak/Ibu diminta memberikan penilaian terhadap setiap butir instrumen berdasarkan kriteria yang tersedia.</p>
            <ul class="mt-3 list-disc space-y-1 pl-5">
                <li>Form ini digunakan untuk menilai kualitas butir instrumen, bukan untuk menjawab survey sebagai responden.</li>
                <li>Gunakan skala penilaian yang disediakan.</li>
                <li>Komentar dapat diberikan untuk membantu perbaikan redaksi, relevansi, atau kejelasan butir.</li>
                <li>Setelah dikirim, isian tidak dapat diubah melalui link ini.</li>
            </ul>
            @if ($round->instructions)
                <p class="mt-4 rounded-md border border-blue-200 bg-white/70 p-3">{{ $round->instructions }}</p>
            @endif
        </div>
        <div data-ui="myriset-section-card" class="rounded-lg border border-slate-200 bg-white p-5 text-sm leading-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Kriteria dan skala</h2>
            <dl class="mt-3 space-y-2">
                @foreach ($criteria as $label => $description)
                    <div>
                        <dt class="font-semibold text-slate-800">{{ $label }}</dt>
                        <dd class="text-slate-600">{{ $description }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($scaleDescription as $scale)
                    <span class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-slate-700">{{ $scale }}</span>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mb-6 rounded-lg border border-slate-200 bg-white p-5 text-sm leading-6 text-slate-600 shadow-sm">
        <h2 class="font-semibold text-slate-950">Apa yang terjadi setelah submit?</h2>
        <p class="mt-2">Masukan Bapak/Ibu akan tersimpan untuk membantu peneliti memperbaiki instrumen. Halaman ini tidak menampilkan data responden, jawaban survei, analisis, halaman admin, token, atau data validator lain.</p>
    </section>

    @if ($errors->any())
        <section class="mb-6 rounded-lg border border-red-200 bg-red-50 p-5 text-sm leading-6 text-red-950" role="alert">
            <h2 class="text-lg font-semibold">Mohon lengkapi penilaian.</h2>
            <p class="mt-1">Periksa butir berikut sebelum mengirim hasil validasi.</p>
            <ul class="mt-3 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($questions->isEmpty())
        <x-myriset.empty-state
            class="bg-white shadow-sm"
            title="This survey has no questions yet."
            description="Validation cannot be submitted until the researcher adds survey questions."
        />
    @else
        <form method="POST" class="space-y-6">
            @csrf

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-950">{{ $questions->count() }} items need scoring</p>
                        <p class="mt-1 text-sm text-slate-600">Pastikan semua butir telah diberi skor sebelum mengirim.</p>
                    </div>
                    <span class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-700">
                        Item 1 of {{ $questions->count() }}
                    </span>
                </div>
            </div>

            @foreach ($questions as $question)
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Butir {{ $loop->iteration }} dari {{ $questions->count() }}</p>
                            <h2 class="mt-1 text-lg font-semibold leading-7 text-slate-950">{{ $question->label }}</h2>
                            @if ($question->help_text)
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $question->help_text }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ str_replace('_', ' ', $question->type) }}</span>
                            @if ($question->scoring?->indicator)
                                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">{{ $question->scoring->indicator->name }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @php
                            $scoreKey = (string) ($loop->iteration - 1);
                        @endphp

                        @foreach ([
                            'content_relevance_score' => 'Content relevance',
                            'language_clarity_score' => 'Language clarity',
                            'construct_alignment_score' => 'Construct alignment',
                            'measurability_score' => 'Measurability',
                            'feasibility_score' => 'Feasibility of use',
                            'ethical_suitability_score' => 'Ethical/privacy suitability',
                        ] as $field => $label)
                            @php
                                $inputId = $field === 'content_relevance_score'
                                    ? 'relevance_score_item_'.$loop->parent->iteration
                                    : $field.'_item_'.$loop->parent->iteration;
                            @endphp
                            <div>
                                <label for="{{ $inputId }}" class="block text-sm font-semibold text-slate-700">{{ $label }}</label>
                                <select id="{{ $inputId }}" name="scores[{{ $scoreKey }}][{{ $field }}]" required class="mt-2 block min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base shadow-sm sm:text-sm">
                                    <option value="">Pilih skor</option>
                                    @for ($score = $round->rating_scale_min; $score <= $round->rating_scale_max; $score++)
                                        <option value="{{ $score }}" @selected(old("scores.{$scoreKey}.{$field}") == $score)>{{ $score }}</option>
                                    @endfor
                                </select>
                                @error("scores.{$scoreKey}.{$field}")
                                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-3">
                        <div>
                            <label for="recommendation_item_{{ $loop->iteration }}" class="block text-sm font-semibold text-slate-700">Rekomendasi</label>
                            <select id="recommendation_item_{{ $loop->iteration }}" name="scores[{{ $scoreKey }}][recommendation]" required class="mt-2 block min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base shadow-sm sm:text-sm">
                                <option value="">Pilih rekomendasi</option>
                                @foreach ($recommendations as $value => $label)
                                    <option value="{{ $value }}" @selected(old("scores.{$scoreKey}.recommendation") === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error("scores.{$scoreKey}.recommendation")
                                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="lg:col-span-2">
                            <label for="comment_item_{{ $loop->iteration }}" class="block text-sm font-semibold text-slate-700">Komentar perbaikan</label>
                            <textarea id="comment_item_{{ $loop->iteration }}" name="scores[{{ $scoreKey }}][comment]" rows="4" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base leading-6 shadow-sm sm:text-sm">{{ old("scores.{$scoreKey}.comment") }}</textarea>
                            <p class="mt-1 text-sm text-slate-500">Opsional. Tuliskan catatan untuk redaksi, relevansi, kejelasan, atau kesesuaian butir.</p>
                        </div>
                    </div>
                </section>
            @endforeach

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Overall recommendation</p>
                <h2 class="mt-1 text-lg font-semibold leading-7 text-slate-950">Final feasibility decision</h2>
                <div class="mt-5 grid gap-4 lg:grid-cols-3">
                    <div>
                        <label for="feasibility_decision" class="block text-sm font-semibold text-slate-700">Final decision</label>
                        <select id="feasibility_decision" name="feasibility_decision" required class="mt-2 block min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base shadow-sm sm:text-sm">
                            <option value="">Pilih keputusan akhir</option>
                            @foreach ($feasibilityDecisions as $value => $label)
                                <option value="{{ $value }}" @selected(old('feasibility_decision') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('feasibility_decision')
                            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="lg:col-span-2">
                        <label for="general_comments" class="block text-sm font-semibold text-slate-700">General comments</label>
                        <textarea id="general_comments" name="general_comments" rows="4" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base leading-6 shadow-sm sm:text-sm">{{ old('general_comments') }}</textarea>
                    </div>
                    <div class="lg:col-span-3">
                        <label for="revision_suggestions" class="block text-sm font-semibold text-slate-700">Suggested revisions</label>
                        <textarea id="revision_suggestions" name="revision_suggestions" rows="4" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base leading-6 shadow-sm sm:text-sm">{{ old('revision_suggestions') }}</textarea>
                        <p class="mt-1 text-sm text-slate-500">Saran ini akan masuk ke revision matrix peneliti.</p>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-emerald-200 bg-white p-5 shadow-sm">
                <p class="text-sm leading-6 text-slate-600">Pastikan semua butir telah diberi skor sebelum mengirim. Setelah dikirim, hasil validasi tidak dapat diubah melalui link ini.</p>
                <button type="submit" class="mt-4 w-full rounded-md bg-emerald-700 px-4 py-3 text-base font-semibold text-white shadow-sm hover:bg-emerald-600">
                    Kirim Hasil Validasi
                </button>
            </section>
        </form>
    @endif
@endsection
