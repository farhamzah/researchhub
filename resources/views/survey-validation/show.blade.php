<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $round->title }} - ResearchHub Validation</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-8 border-b border-gray-200 pb-6">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">ResearchHub Expert Validation</p>
            <h1 class="mt-2 text-3xl font-semibold">{{ $round->title }}</h1>
            <p class="mt-3 text-base leading-7 text-gray-600">{{ $survey->title }}</p>
            <p class="mt-1 text-sm text-gray-500">{{ $project?->title ?? 'Research project' }}</p>
        </header>

        <section class="mb-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Validator</p>
                <p class="mt-2 font-semibold">{{ $validator->name }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ $assignment->role ?: 'Expert validator' }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Skala Penilaian</p>
                <p class="mt-2 font-semibold">{{ $round->rating_scale_min }} sampai {{ $round->rating_scale_max }}</p>
                <p class="mt-1 text-sm text-gray-500">Nilai lebih tinggi berarti lebih layak.</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Batas Akses</p>
                <p class="mt-2 font-semibold">{{ $assignment->expires_at?->format('Y-m-d H:i') ?? 'Tidak ada batas khusus' }}</p>
                <p class="mt-1 text-sm text-gray-500">Link ini hanya untuk penugasan validator ini.</p>
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-5 text-sm leading-6 text-blue-950">
            <h2 class="font-semibold">Petunjuk</h2>
            <p class="mt-2">{{ $round->instructions ?: 'Mohon menilai setiap butir instrumen berdasarkan relevansi, kejelasan, bahasa, dan kelayakan.' }}</p>
            <div class="mt-3 grid gap-2 md:grid-cols-2">
                <p>1 = Tidak relevan / tidak layak</p>
                <p>2 = Kurang relevan / perlu revisi besar</p>
                <p>3 = Relevan / perlu revisi kecil</p>
                <p>4 = Sangat relevan / layak</p>
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-5 text-sm text-gray-600 shadow-sm">
            Form ini hanya menampilkan konteks instrumen yang perlu divalidasi. Data responden, jawaban survei, analisis, dan halaman admin tidak ditampilkan.
        </section>

        @if ($errors->any())
            <section class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Mohon lengkapi penilaian.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($questions->isEmpty())
            <section class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center shadow-sm">
                <h2 class="text-xl font-semibold">This survey has no questions yet.</h2>
                <p class="mt-2 text-sm text-gray-600">Validation cannot be submitted until the researcher adds survey questions.</p>
            </section>
        @else
            <form method="POST" action="{{ route('validation.survey.store', ['token' => $token]) }}" class="space-y-6">
                @csrf

                @foreach ($questions as $question)
                    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Butir {{ $loop->iteration }}</p>
                                <h2 class="mt-1 text-lg font-semibold">{{ $question->label }}</h2>
                                @if ($question->help_text)
                                    <p class="mt-2 text-sm text-gray-600">{{ $question->help_text }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">{{ str_replace('_', ' ', $question->type) }}</span>
                                @if ($question->scoring?->indicator)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">{{ $question->scoring->indicator->name }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-4">
                            @foreach ([
                                'relevance_score' => 'Relevansi',
                                'clarity_score' => 'Kejelasan',
                                'language_score' => 'Bahasa',
                                'appropriateness_score' => 'Kelayakan',
                            ] as $field => $label)
                                <div>
                                    <label for="{{ $field }}_{{ $question->id }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
                                    <select id="{{ $field }}_{{ $question->id }}" name="scores[{{ $question->id }}][{{ $field }}]" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                        <option value="">Pilih</option>
                                        @for ($score = $round->rating_scale_min; $score <= $round->rating_scale_max; $score++)
                                            <option value="{{ $score }}" @selected(old("scores.{$question->id}.{$field}") == $score)>{{ $score }}</option>
                                        @endfor
                                    </select>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-3">
                            <div>
                                <label for="recommendation_{{ $question->id }}" class="block text-sm font-medium text-gray-700">Rekomendasi</label>
                                <select id="recommendation_{{ $question->id }}" name="scores[{{ $question->id }}][recommendation]" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                    <option value="">Pilih rekomendasi</option>
                                    @foreach ($recommendations as $value => $label)
                                        <option value="{{ $value }}" @selected(old("scores.{$question->id}.recommendation") === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label for="comment_{{ $question->id }}" class="block text-sm font-medium text-gray-700">Komentar</label>
                                <textarea id="comment_{{ $question->id }}" name="scores[{{ $question->id }}][comment]" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old("scores.{$question->id}.comment") }}</textarea>
                            </div>
                        </div>
                    </section>
                @endforeach

                <button type="submit" class="w-full rounded-md bg-emerald-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                    Kirim Penilaian Validasi
                </button>
            </form>
        @endif
    </main>
</body>
</html>
