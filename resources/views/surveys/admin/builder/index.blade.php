@php
    use App\Models\Survey;
    use App\Models\SurveyQuestion;

    $label = fn (?string $value): string => str($value ?: 'not_set')->replace('_', ' ')->title()->toString();
    $typeClass = fn (string $type): string => match ($type) {
        SurveyQuestion::TYPE_SINGLE_CHOICE, SurveyQuestion::TYPE_MULTIPLE_CHOICE => 'border-blue-200 bg-blue-50 text-blue-700',
        SurveyQuestion::TYPE_LIKERT, SurveyQuestion::TYPE_LIKERT_MATRIX => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        SurveyQuestion::TYPE_NUMBER, SurveyQuestion::TYPE_DATE => 'border-cyan-200 bg-cyan-50 text-cyan-700',
        SurveyQuestion::TYPE_CONSENT => 'border-amber-200 bg-amber-50 text-amber-800',
        SurveyQuestion::TYPE_HIDDEN => 'border-red-200 bg-red-50 text-red-700',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };
    $json = fn ($value) => $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
    $choices = function (?SurveyQuestion $question, int $slots = 6): array {
        $options = $question?->options ?? [];
        $values = array_is_list($options) ? $options : ($options['choices'] ?? $options['options'] ?? []);
        $values = array_values(array_map(fn ($choice): string => is_array($choice)
            ? (string) ($choice['label'] ?? $choice['value'] ?? '')
            : (string) $choice, is_array($values) ? $values : []));

        return array_pad(array_slice($values, 0, $slots), $slots, '');
    };
    $scale = function (?SurveyQuestion $question): array {
        $options = $question?->options ?? [];
        $settings = $question?->settings ?? [];
        $values = $settings['scale'] ?? $options['scale'] ?? config('researchhub_surveys.default_likert_scale', [1, 2, 3, 4, 5]);

        return array_pad(array_slice(array_map('strval', is_array($values) ? $values : []), 0, 5), 5, '');
    };
    $matrixRows = function (?SurveyQuestion $question): array {
        $rows = $question?->options['rows'] ?? [];

        return array_pad(array_slice(array_map('strval', is_array($rows) ? $rows : []), 0, 12), 12, '');
    };
    $matrixColumns = function (?SurveyQuestion $question): array {
        $options = $question?->options ?? [];
        $settings = $question?->settings ?? [];
        $columns = $options['columns'] ?? $settings['scale'] ?? config('researchhub_surveys.default_likert_scale', [1, 2, 3, 4, 5]);

        $columns = collect(is_array($columns) ? $columns : [])
            ->map(fn ($column, $index): array => is_array($column)
                ? ['value' => (string) ($column['value'] ?? $index + 1), 'label' => (string) ($column['label'] ?? $column['value'] ?? '')]
                : ['value' => (string) ($index + 1), 'label' => (string) $column])
            ->values()
            ->all();

        return array_pad(array_slice($columns, 0, 7), 7, ['value' => '', 'label' => '']);
    };
    $bulkPreview = session('bulk_question_preview');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Survey Builder - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section data-ui="myriset-page-header" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Admin</p>
                    <h1 class="mt-2 text-3xl font-semibold">Survey Builder</h1>
                    <p class="mt-2 text-lg font-semibold text-slate-800">{{ $survey->title }}</p>
                    <p class="mt-1 text-sm leading-6 text-slate-600">
                        Bangun instrumen survey untuk {{ $survey->project?->title ?: 'project belum dipilih' }} dengan alur setup, indikator, pertanyaan, skoring, preview, validasi, dan analisis.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('filament.admin.resources.surveys.index') }}" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                        Edit Survey
                    </a>
                    <a href="{{ route('filament.admin.resources.surveys.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Back to Surveys
                    </a>
                    <a href="{{ route('admin.surveys.readability.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Readability Test
                    </a>
                    <a href="{{ route('admin.surveys.supervisor-review.index', ['survey' => $survey]) }}" class="rounded-md border border-indigo-300 bg-white px-4 py-2 text-sm font-semibold text-indigo-800 shadow-sm hover:bg-indigo-50">
                        Supervisor Review
                    </a>
                    <a href="{{ route('admin.surveys.analysis.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Analysis Dashboard
                    </a>
                    <a href="{{ route('admin.surveys.distribution.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Distribution Center
                    </a>
                    <a href="{{ route('admin.surveys.collection-monitoring.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Collection Monitoring
                    </a>
                    <a href="{{ route('admin.surveys.analysis-package.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Analysis Package
                    </a>
                    <a href="{{ route('admin.surveys.preflight.index', ['survey' => $survey]) }}" class="rounded-md border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">
                        Preflight QA
                    </a>
                    <a href="{{ route('admin.surveys.respondent-package.index', ['survey' => $survey]) }}" class="rounded-md border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-900 shadow-sm hover:bg-amber-50">
                        Respondent Package
                    </a>
                    @if ($survey->project)
                        <a href="{{ route('admin.projects.journey.show', ['researchProject' => $survey->project]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            Open Project Journey
                        </a>
                    @endif
                </div>
            </div>

            <nav aria-label="Survey builder wizard steps" class="mt-6 grid gap-2 md:grid-cols-7">
                @foreach ($builderWizard['steps'] as $step)
                    <a href="#{{ $step['anchor'] }}" class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm hover:border-emerald-200 hover:bg-emerald-50">
                        <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Step {{ $loop->iteration }}</span>
                        <span class="mt-1 block font-semibold text-slate-950">{{ $step['label'] }}</span>
                        <x-myriset.status-badge :status="$step['status']" :label="$step['status']" size="xs" class="mt-2" />
                    </a>
                @endforeach
            </nav>
        </section>

        @if (session('status'))
            <section class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                {{ str_replace('-', ' ', session('status')) }}
            </section>
        @endif

        @if ($errors->any())
            <section class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Please check the builder form.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($hasResponses)
            <section class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                Survey ini sudah memiliki respons. Perubahan struktur pertanyaan dibatasi agar data tetap konsisten.
            </section>
        @endif

        <section id="setup-survey" class="mt-6 scroll-mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Setup Survey</p>
                    <h2 class="mt-1 text-xl font-semibold">Apa yang sedang dikerjakan?</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $survey->description ?: 'Deskripsi survey belum diisi.' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($survey->canReceiveResponses())
                        <a href="{{ route('survey.show', ['survey' => $survey->slug]) }}" target="_blank" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            Open Public Survey
                        </a>
                    @else
                        <span class="rounded-md border border-slate-200 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-500">
                            Public survey not open
                        </span>
                    @endif
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Project</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950">{{ $survey->project?->title ?: 'Not assigned' }}</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                    <x-myriset.status-badge :status="$survey->status" :label="$label($survey->status)" class="mt-2" />
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Identity Mode</p>
                    <x-myriset.status-badge :status="$survey->identity_mode" :label="$label($survey->identity_mode)" class="mt-2" />
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Questions / Responses</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950">{{ $builderWizard['setup']['question_count'] }} questions, {{ $builderWizard['setup']['response_count'] }} responses</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4 lg:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Validation Status</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950">{{ $builderWizard['setup']['validation_status'] }}</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4 lg:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Analysis Status</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950">{{ $builderWizard['setup']['analysis_status'] }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.surveys.builder.instrument-summary.update', ['survey' => $survey]) }}" class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-5">
                @csrf
                @method('PUT')
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Survey Instrument Summary</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-950">{{ $survey->instrument_summary_override ? 'Manual summary active' : 'Auto summary active' }}</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Auto summary stays available as fallback. Use the manual field when the researcher needs dissertation-ready wording.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" name="summary_action" value="generate" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Generate Summary</button>
                        <button type="submit" name="summary_action" value="use_manual" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Use Manual Summary</button>
                        <button type="submit" name="summary_action" value="clear_manual" class="rounded-md border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 shadow-sm hover:bg-red-50">Clear Manual Summary</button>
                    </div>
                </div>
                <textarea name="instrument_summary_override" rows="5" class="mt-4 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm leading-6 shadow-sm" placeholder="Write a manual instrument summary for proposal/dissertation documentation.">{{ old('instrument_summary_override', $survey->instrument_summary_override ?: $academicNarratives['surveyInstrument']) }}</textarea>
            </form>

            <form method="POST" action="{{ route('admin.surveys.builder.intro.update', ['survey' => $survey]) }}" enctype="multipart/form-data" class="mt-5 rounded-lg border border-emerald-100 bg-emerald-50/60 p-5">
                @csrf
                @method('PUT')
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Opening / Introduction</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-950">Intro sebelum pertanyaan</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Intro muncul sebelum pertanyaan pada public survey. Kosongkan narasi intro jika survey tidak membutuhkan halaman pembuka.</p>
                    </div>
                    <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                        Save Intro
                    </button>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="intro_title" class="block text-sm font-medium text-slate-700">Intro title</label>
                        <input id="intro_title" name="intro_title" value="{{ old('intro_title', $survey->intro_title) }}" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div>
                        <label for="estimated_duration" class="block text-sm font-medium text-slate-700">Estimated completion time</label>
                        <input id="estimated_duration" name="estimated_duration" value="{{ old('estimated_duration', $survey->estimated_duration) }}" placeholder="10-15 menit" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div class="lg:col-span-2">
                        <div class="rounded-md border border-emerald-200 bg-white p-4">
                            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
                                <div>
                                    <label for="intro_image" class="block text-sm font-medium text-slate-700">Intro illustration image</label>
                                    <input id="intro_image" name="intro_image" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" class="mt-2 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                                    @error('intro_image')
                                        <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-2 text-xs leading-5 text-slate-500">JPG, JPEG, PNG, or WEBP. Max 2MB. Recommended 16:9, 1200x675 or 1600x900. Use neutral research context imagery that does not imply a preferred answer.</p>

                                    @if ($survey->intro_image_path)
                                        <label class="mt-4 flex items-start gap-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm leading-6 text-red-950">
                                            <input type="checkbox" name="remove_intro_image" value="1" @checked(old('remove_intro_image')) class="mt-1 rounded border-red-300 text-red-700">
                                            <span>Remove current intro image</span>
                                        </label>
                                    @endif
                                </div>

                                <div>
                                    @if ($survey->intro_image_url)
                                        <figure class="overflow-hidden rounded-xl border bg-slate-50">
                                            <img src="{{ $survey->intro_image_url }}" alt="{{ $survey->intro_image_alt_text ?: 'Survey intro illustration' }}" class="max-h-80 w-full object-contain">
                                        </figure>
                                    @elseif ($survey->intro_image_path)
                                        <div class="flex aspect-video items-center justify-center rounded-md border border-amber-200 bg-amber-50 p-4 text-center text-sm leading-6 text-amber-900">Intro image path is saved, but the file could not be found in public storage.</div>
                                    @else
                                        <div class="flex aspect-video items-center justify-center rounded-md border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-500">No intro image uploaded</div>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                <div>
                                    <label for="intro_image_alt_text" class="block text-sm font-medium text-slate-700">Image alt text</label>
                                    <input id="intro_image_alt_text" name="intro_image_alt_text" value="{{ old('intro_image_alt_text', $survey->intro_image_alt_text) }}" placeholder="Ilustrasi responden membaca pengantar survey penelitian" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                    @error('intro_image_alt_text')
                                        <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="intro_image_source_note" class="block text-sm font-medium text-slate-700">Credit/source note</label>
                                    <input id="intro_image_source_note" name="intro_image_source_note" value="{{ old('intro_image_source_note', $survey->intro_image_source_note) }}" placeholder="Dokumentasi peneliti / licensed image source" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                </div>
                                <div class="lg:col-span-2">
                                    <label for="intro_image_caption" class="block text-sm font-medium text-slate-700">Image caption</label>
                                    <textarea id="intro_image_caption" name="intro_image_caption" rows="2" placeholder="Gambar bersifat ilustratif dan tidak memengaruhi jawaban responden." class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm leading-6 shadow-sm">{{ old('intro_image_caption', $survey->intro_image_caption) }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="intro_text" class="block text-sm font-medium text-slate-700">Intro narrative / explanation</label>
                        <textarea id="intro_text" name="intro_text" rows="4" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm leading-6 shadow-sm">{{ old('intro_text', $survey->intro_text) }}</textarea>
                    </div>
                    <div>
                        <label for="privacy_statement" class="block text-sm font-medium text-slate-700">Privacy/confidentiality statement</label>
                        <textarea id="privacy_statement" name="privacy_statement" rows="3" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm leading-6 shadow-sm">{{ old('privacy_statement', $survey->privacy_statement) }}</textarea>
                    </div>
                    <div>
                        <label for="respondent_instruction" class="block text-sm font-medium text-slate-700">Respondent instruction</label>
                        <textarea id="respondent_instruction" name="respondent_instruction" rows="3" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm leading-6 shadow-sm">{{ old('respondent_instruction', $survey->respondent_instruction) }}</textarea>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="consent_text" class="block text-sm font-medium text-slate-700">Consent checkbox text</label>
                        <textarea id="consent_text" name="consent_text" rows="2" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm leading-6 shadow-sm">{{ old('consent_text', $survey->consent_text ?: 'Saya telah membaca penjelasan di atas dan bersedia melanjutkan.') }}</textarea>
                    </div>
                    <input type="hidden" name="require_consent_before_start" value="0">
                    <label class="flex items-start gap-3 rounded-md border border-emerald-200 bg-white p-4 text-sm leading-6 text-slate-700 lg:col-span-2">
                        <input type="checkbox" name="require_consent_before_start" value="1" @checked(old('require_consent_before_start', $survey->require_consent_before_start)) class="mt-1 rounded border-slate-300 text-emerald-700">
                        <span>
                            <span class="block font-semibold text-slate-900">Require consent before showing questions</span>
                            <span class="block text-slate-600">Respondents must check the intro consent box before continuing to the question form.</span>
                        </span>
                    </label>
                </div>
            </form>

            <div class="mt-5 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-900">
                Identity mode controls whether respondent identity is collected or anonymized. Do not add sensitive personal data questions unless required by protocol and ethics approval.
            </div>
        </section>

        <x-academic-output-block
            class="mt-6"
            title="Survey Instrument Summary"
            description="Narasi akademik non-AI berdasarkan struktur instrumen, indikator, tipe pertanyaan, dan konfigurasi skoring."
            :narrative="$academicNarratives['surveyInstrument']"
            source="Sumber: Survey Builder"
        />

        <section id="indikator" class="mt-6 scroll-mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Indikator</p>
                    <h2 class="mt-1 text-xl font-semibold">Indikator dan skala</h2>
                    <p class="mt-1 text-sm text-slate-600">Gunakan indikator agar skoring dan analisis survey lebih mudah dibaca.</p>
                </div>
                <a href="{{ route('admin.surveys.scoring.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Manage Scoring
                </a>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @forelse ($builderWizard['indicators'] as $indicator)
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <h3 class="font-semibold text-slate-950">{{ $indicator['name'] }}</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ $indicator['description'] ?: 'No description yet. Add one from Manage Scoring.' }}</p>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Scale</dt>
                                <dd class="font-semibold text-slate-800">{{ $indicator['scale'] ?: 'No scale' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Linked questions</dt>
                                <dd class="font-semibold text-slate-800">{{ $indicator['linked_question_count'] }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Average score</dt>
                                <dd class="font-semibold text-slate-800">{{ $indicator['average_score'] !== null ? number_format((float) $indicator['average_score'], 2) : 'Not available' }}</dd>
                            </div>
                        </dl>
                    </article>
                @empty
                    <x-myriset.empty-state
                        class="md:col-span-2 xl:col-span-4"
                        title="Belum ada indikator"
                        description="Tambahkan indikator agar skoring, validasi ahli, dan analisis survey lebih mudah dibaca oleh peneliti."
                    />
                @endforelse
            </div>
        </section>

        <section id="pertanyaan" class="mt-6 scroll-mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Pertanyaan</p>
                    <h2 class="mt-1 text-xl font-semibold">Question List</h2>
                    <p class="mt-1 text-sm text-slate-600">Questions are displayed in the order respondents will see them.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="#add-question" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                        Add Question
                    </a>
                    <span class="rounded-md border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700">{{ $survey->questions->count() }} questions</span>
                </div>
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-[0.42fr_0.58fr]">
                <section class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                    <h3 class="text-lg font-semibold">Pages</h3>
                    <p class="mt-1 text-sm text-slate-600">Optional sections help group long instruments.</p>

                    <form method="POST" action="{{ route('admin.surveys.builder.pages.store', ['survey' => $survey]) }}" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label for="page_title" class="block text-sm font-medium text-slate-700">Page title</label>
                            <input id="page_title" name="title" value="{{ old('title') }}" placeholder="Section A: Respondent profile" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="page_description" class="block text-sm font-medium text-slate-700">Description</label>
                            <textarea id="page_description" name="description" rows="3" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('description') }}</textarea>
                        </div>
                        <div>
                            <label for="page_sort_order" class="block text-sm font-medium text-slate-700">Order</label>
                            <input id="page_sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $survey->pages->count() + 1) }}" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                            Add Page
                        </button>
                    </form>

                    <div class="mt-6 space-y-3">
                        @forelse ($survey->pages as $page)
                            <form method="POST" action="{{ route('admin.surveys.builder.pages.update', ['survey' => $survey, 'page' => $page]) }}" class="rounded-md border border-slate-200 bg-white p-4">
                                @csrf
                                @method('PUT')
                                <div class="grid gap-3">
                                    <input name="title" value="{{ $page->title }}" placeholder="Untitled page" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                    <input name="description" value="{{ $page->description }}" title="{{ $page->description }}" placeholder="Description" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                    @if ($page->description)
                                        <p class="truncate text-xs leading-5 text-slate-500" title="{{ $page->description }}">{{ $page->description }}</p>
                                    @endif
                                    <input name="sort_order" type="number" min="0" value="{{ $page->sort_order }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Save Page</button>
                                    @if (! $hasResponses)
                                        <button form="delete-page-{{ $page->id }}" type="submit" onclick="return confirm('Delete this survey page? Questions remain in the survey unless moved separately.')" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">Delete</button>
                                    @else
                                        <span class="self-center text-xs font-semibold text-slate-500">Delete locked</span>
                                    @endif
                                </div>
                            </form>
                            <form id="delete-page-{{ $page->id }}" method="POST" action="{{ route('admin.surveys.builder.pages.delete', ['survey' => $survey, 'page' => $page]) }}">
                                @csrf
                                @method('DELETE')
                            </form>
                        @empty
                            <p class="rounded-md border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500">No pages yet. Questions can still be created without a page.</p>
                        @endforelse
                    </div>
                </section>

                <section id="add-question" class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                    <h3 class="text-lg font-semibold">Add Question</h3>
                    <p class="mt-1 text-sm text-slate-600">Use structured choice fields for common research instrument questions. Advanced JSON remains available for edge cases.</p>

                    <form method="POST" action="{{ route('admin.surveys.builder.questions.store', ['survey' => $survey]) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                        @csrf
                        @include('surveys.admin.builder.partials.question-form', [
                            'question' => null,
                            'survey' => $survey,
                            'questionTypes' => $questionTypes,
                            'hasResponses' => false,
                            'json' => $json,
                            'choices' => $choices,
                            'scale' => $scale,
                            'matrixRows' => $matrixRows,
                            'matrixColumns' => $matrixColumns,
                        ])
                        <button type="submit" class="md:col-span-2 w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                            Add Question
                        </button>
                    </form>
                </section>
            </div>

            <section id="bulk-add-questions" class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-800">Bulk Add Questions</p>
                        <h3 class="mt-1 text-lg font-semibold text-blue-950">Paste text or JSON instrument sections</h3>
                        <p class="mt-1 text-sm leading-6 text-blue-900">Preview before import. Imports are transactional and duplicate question keys stop the whole import.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.surveys.builder.templates.pharmvr-student-needs.fill-missing', ['survey' => $survey]) }}">
                            @csrf
                            <button type="submit" @disabled($hasResponses) onclick="return confirm('Fill only missing PharmVR sections? Existing question keys will not be overwritten.')" class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-900 shadow-sm hover:bg-blue-100 disabled:bg-slate-300">Fill Missing PharmVR Sections</button>
                        </form>
                        <form method="POST" action="{{ route('admin.surveys.builder.templates.pharmvr-student-needs.normalize', ['survey' => $survey]) }}">
                            @csrf
                            <button type="submit" @disabled($hasResponses || $pharmVrNormalizationPreview['change_count'] === 0) onclick="return confirm('Normalize existing PharmVR wording? This only runs when the survey has zero responses and will preserve question IDs.')" class="rounded-md border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-900 shadow-sm hover:bg-emerald-50 disabled:bg-slate-300">Normalize PharmVR Student Survey Wording</button>
                        </form>
                        <form method="POST" action="{{ route('admin.surveys.builder.templates.pharmvr-student-needs', ['survey' => $survey]) }}">
                            @csrf
                            <button type="submit" @disabled($hasResponses) onclick="return confirm('{{ $survey->questions->isNotEmpty() ? 'This survey already has questions. Creating the full template will be blocked if duplicate keys are found. Continue?' : 'Create the full PharmVR Student Needs Survey template in this survey?' }}')" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 disabled:bg-slate-300">Create PharmVR Student Needs Survey</button>
                        </form>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded-md border border-blue-100 bg-white p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">PharmVR template keys</p>
                        <p class="mt-1 text-sm text-blue-950">{{ $pharmVrTemplatePreview['existing_count'] }} existing / {{ $pharmVrTemplatePreview['template_count'] }} total</p>
                    </div>
                    <div class="rounded-md border border-emerald-100 bg-white p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Missing PharmVR keys</p>
                        <p class="mt-1 text-sm text-emerald-950">{{ $pharmVrTemplatePreview['missing_count'] }} keys</p>
                        @if ($pharmVrTemplatePreview['missing_count'] > 0)
                            <p class="mt-1 truncate text-xs text-emerald-800" title="{{ implode(', ', $pharmVrTemplatePreview['missing_keys']) }}">{{ implode(', ', array_slice($pharmVrTemplatePreview['missing_keys'], 0, 14)) }}{{ $pharmVrTemplatePreview['missing_count'] > 14 ? ', ...' : '' }}</p>
                        @endif
                    </div>
                    <div class="rounded-md border border-slate-200 bg-white p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fill missing pages</p>
                        <p class="mt-1 truncate text-sm text-slate-700" title="{{ implode(', ', $pharmVrTemplatePreview['missing_pages']) }}">{{ $pharmVrTemplatePreview['missing_pages'] === [] ? 'No missing sections' : implode(', ', $pharmVrTemplatePreview['missing_pages']) }}</p>
                    </div>
                </div>

                <div class="mt-4 rounded-md border border-emerald-100 bg-white p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Normalize PharmVR wording preview</p>
                            <p class="mt-1 text-sm text-slate-700">{{ $pharmVrNormalizationPreview['change_count'] }} question updates detected; {{ count($pharmVrNormalizationPreview['missing_keys']) }} approved keys missing.</p>
                            @if ($pharmVrNormalizationPreview['response_count'] > 0)
                                <p class="mt-1 text-sm font-semibold text-red-700">Normalization is blocked because this survey already has responses.</p>
                            @endif
                        </div>
                        <span class="rounded-md border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">{{ $pharmVrNormalizationPreview['response_count'] }} responses</span>
                    </div>
                    @if ($pharmVrNormalizationPreview['changes'] !== [])
                        <div class="mt-3 max-h-28 overflow-y-auto text-xs leading-5 text-slate-600">
                            @foreach (array_slice($pharmVrNormalizationPreview['changes'], 0, 8) as $change)
                                <p><span class="font-mono font-semibold">{{ $change['key'] }}</span>: {{ implode(', ', $change['fields']) }}</p>
                            @endforeach
                            @if ($pharmVrNormalizationPreview['change_count'] > 8)
                                <p>+ {{ $pharmVrNormalizationPreview['change_count'] - 8 }} more changes</p>
                            @endif
                        </div>
                    @endif
                    @if ($pharmVrNormalizationPreview['missing_keys'] !== [])
                        <p class="mt-3 truncate text-xs text-amber-800" title="{{ implode(', ', $pharmVrNormalizationPreview['missing_keys']) }}">Missing approved keys: {{ implode(', ', $pharmVrNormalizationPreview['missing_keys']) }}</p>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.surveys.builder.bulk-questions.preview', ['survey' => $survey]) }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="bulk_input" class="block text-sm font-medium text-blue-950">Bulk input</label>
                        <textarea id="bulk_input" name="bulk_input" rows="12" class="mt-2 block w-full rounded-md border border-blue-200 px-3 py-2 font-mono text-xs leading-6 shadow-sm" placeholder="PAGE: Pengalaman Pembelajaran CPOB/GMP&#10;PAGE_ORDER: 3&#10;INDICATOR: Pengalaman Pembelajaran CPOB/GMP&#10;TYPE: likert&#10;REQUIRED: true&#10;SCALE: 1,2,3,4,5&#10;HELP: Pilih jawaban sesuai tingkat persetujuan Anda.&#10;&#10;C1 | Saya telah memperoleh materi dasar mengenai CPOB/GMP.">{{ old('bulk_input') }}</textarea>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                        <select name="indicator_strategy" class="rounded-md border border-blue-200 px-3 py-2 text-sm shadow-sm">
                            <option value="create" @selected(old('indicator_strategy', 'create') === 'create')>Create missing indicator</option>
                            <option value="skip" @selected(old('indicator_strategy') === 'skip')>Skip indicator link</option>
                            <option value="cancel" @selected(old('indicator_strategy') === 'cancel')>Cancel if indicator missing</option>
                        </select>
                        <button type="submit" @disabled($hasResponses) class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-900 shadow-sm hover:bg-blue-100 disabled:bg-slate-300">Preview</button>
                        <button type="submit" formaction="{{ route('admin.surveys.builder.bulk-questions.import', ['survey' => $survey]) }}" @disabled($hasResponses) class="rounded-md bg-blue-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 disabled:bg-slate-300">Import</button>
                    </div>
                </form>

                @if (is_array($bulkPreview))
                    <div class="mt-5 rounded-md border border-blue-200 bg-white p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-950">Bulk import preview</p>
                                <p class="mt-1 text-sm text-slate-600">Page: {{ $bulkPreview['page']['title'] }} ({{ $bulkPreview['page_exists'] ? 'existing page will be reused' : 'will be created' }})</p>
                                <p class="mt-1 text-sm text-slate-600">Page order: {{ $bulkPreview['page']['order'] ?: 'next available' }}</p>
                                <p class="mt-1 text-sm text-slate-600">Question type: {{ str($bulkPreview['question_type'])->replace('_', ' ')->title() }} | Required: {{ $bulkPreview['required'] ? 'Yes' : 'No' }}</p>
                                <p class="mt-1 text-sm text-slate-600">Indicator input: {{ $bulkPreview['indicator'] ?: 'none' }}</p>
                            </div>
                            <span class="rounded-md border border-blue-200 bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-900">{{ $bulkPreview['question_count'] }} questions</span>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                            <div class="rounded-md border border-emerald-100 bg-emerald-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Existing indicators used</p>
                                <p class="mt-1 text-sm text-emerald-950">{{ collect($bulkPreview['existing_indicators_used'])->join(', ') ?: 'None' }}</p>
                            </div>
                            <div class="rounded-md border border-blue-100 bg-blue-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">New indicators to create</p>
                                <p class="mt-1 text-sm text-blue-950">{{ collect($bulkPreview['new_indicators_to_create'])->join(', ') ?: 'None' }}</p>
                            </div>
                            <div class="rounded-md border border-amber-100 bg-amber-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Possible duplicates</p>
                                <p class="mt-1 text-sm text-amber-950">{{ collect($bulkPreview['possible_duplicate_indicators'])->join(', ') ?: 'None' }}</p>
                            </div>
                        </div>
                        @if ($bulkPreview['warnings'] !== [])
                            <ul class="mt-3 list-disc pl-5 text-sm leading-6 text-amber-800">
                                @foreach ($bulkPreview['warnings'] as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="mt-4 max-h-72 overflow-y-auto rounded-md border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Key</th>
                                        <th class="px-3 py-2">Question</th>
                                        <th class="px-3 py-2">Indicator</th>
                                        <th class="px-3 py-2">Scoring</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach ($bulkPreview['questions'] as $previewQuestion)
                                        @php $previewScoring = collect($bulkPreview['scoring'])->firstWhere('key', $previewQuestion['key']); @endphp
                                        <tr>
                                            <td class="px-3 py-2 font-mono text-xs">{{ $previewQuestion['key'] }}</td>
                                            <td class="px-3 py-2">{{ $previewQuestion['text'] }}</td>
                                            <td class="px-3 py-2">{{ $previewScoring['indicator'] ?? 'None' }}</td>
                                            <td class="px-3 py-2">{{ $previewScoring['status'] ?? 'Descriptive' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </section>

            <div class="mt-6 space-y-5">
                @forelse ($survey->questions as $question)
                    @php
                        $card = collect($builderWizard['questions'])->firstWhere('id', $question->id);
                    @endphp
                    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['order_label'] }}</p>
                                <h3 class="mt-1 text-lg font-semibold text-slate-950">{{ $question->label }}</h3>
                                @if ($question->help_text)
                                    <p class="mt-1 text-sm text-slate-600">{{ $question->help_text }}</p>
                                @endif
                                <p class="mt-2 text-xs text-slate-500">Option count: {{ $card['option_count'] }} | Scoring: {{ $card['scoring_status'] }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $typeClass($question->type) }}">{{ $label($question->type) }}</span>
                                <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $question->is_required ? 'border-red-200 bg-red-50 text-red-700' : 'border-slate-200 bg-slate-50 text-slate-700' }}">{{ $question->is_required ? 'Required' : 'Optional' }}</span>
                                @if ($question->scoring?->indicator)
                                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Indicator: {{ $question->scoring->indicator->name }}</span>
                                @else
                                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">No indicator</span>
                                @endif
                            </div>
                        </div>

                        @if ($card['options_preview'] !== [])
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach ($card['options_preview'] as $option)
                                    <span class="rounded-md border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">{{ $option }}</span>
                                @endforeach
                            </div>
                        @endif

                        <details class="mt-5 rounded-md border border-slate-200 bg-slate-50">
                            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-700">Edit question</summary>
                            <form method="POST" action="{{ route('admin.surveys.builder.questions.update', ['survey' => $survey, 'question' => $question]) }}" class="grid gap-4 border-t border-slate-200 p-4 md:grid-cols-2">
                                @csrf
                                @method('PUT')
                                @include('surveys.admin.builder.partials.question-form', [
                                    'question' => $question,
                                    'survey' => $survey,
                                    'questionTypes' => $questionTypes,
                                    'hasResponses' => $hasResponses,
                                    'json' => $json,
                                    'choices' => $choices,
                                    'scale' => $scale,
                                    'matrixRows' => $matrixRows,
                                    'matrixColumns' => $matrixColumns,
                                ])
                                <div class="md:col-span-2 flex flex-wrap gap-2">
                                    <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Save Question</button>
                                    <button form="move-up-question-{{ $question->id }}" type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Move Up</button>
                                    <button form="move-down-question-{{ $question->id }}" type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Move Down</button>
                                    <button form="duplicate-question-{{ $question->id }}" type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Duplicate</button>
                                    @if (! $hasResponses)
                                        <button form="delete-question-{{ $question->id }}" type="submit" onclick="return confirm('Delete this question? This cannot be undone.')" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">Delete</button>
                                    @else
                                        <span class="self-center text-xs font-semibold text-slate-500">Delete locked</span>
                                    @endif
                                </div>
                            </form>
                        </details>

                        @foreach (['up' => max(0, $question->sort_order - 1), 'down' => $question->sort_order + 1] as $direction => $sortOrder)
                            <form id="move-{{ $direction }}-question-{{ $question->id }}" method="POST" action="{{ route('admin.surveys.builder.questions.update', ['survey' => $survey, 'question' => $question]) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="page_id" value="{{ $question->page_id }}">
                                <input type="hidden" name="question_key" value="{{ $question->question_key }}">
                                <input type="hidden" name="type" value="{{ $question->type }}">
                                <input type="hidden" name="label" value="{{ $question->label }}">
                                <input type="hidden" name="help_text" value="{{ $question->help_text }}">
                                <input type="hidden" name="options_json" value="{{ $json($question->options) }}">
                                <input type="hidden" name="settings_json" value="{{ $json($question->settings) }}">
                                <input type="hidden" name="is_required" value="{{ $question->is_required ? 1 : 0 }}">
                                <input type="hidden" name="sort_order" value="{{ $sortOrder }}">
                            </form>
                        @endforeach

                        <form id="duplicate-question-{{ $question->id }}" method="POST" action="{{ route('admin.surveys.builder.questions.duplicate', ['survey' => $survey, 'question' => $question]) }}">
                            @csrf
                        </form>
                        <form id="delete-question-{{ $question->id }}" method="POST" action="{{ route('admin.surveys.builder.questions.delete', ['survey' => $survey, 'question' => $question]) }}">
                            @csrf
                            @method('DELETE')
                        </form>
                    </article>
                @empty
                    <x-myriset.empty-state
                        title="Belum ada pertanyaan"
                        description="Mulai dengan pertanyaan short text, single choice, atau Likert agar instrumen survey siap dipreview sebelum dikirim ke responden."
                        action-url="#add-question"
                        action-label="Create Question"
                    />
                @endforelse
            </div>
        </section>

        <section id="skoring" class="mt-6 scroll-mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Skoring</p>
                    <h2 class="mt-1 text-xl font-semibold">Scoring readiness</h2>
                    <p class="mt-1 text-sm text-slate-600">Ringkasan konfigurasi skoring untuk pertanyaan yang bisa dinilai.</p>
                </div>
                <a href="{{ route('admin.surveys.scoring.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Open Scoring
                </a>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-4">
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Scoreable</p>
                    <p class="mt-2 text-lg font-semibold">{{ $builderWizard['scoring']['total_scoreable'] }}</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">With indicator</p>
                    <p class="mt-2 text-lg font-semibold">{{ $builderWizard['scoring']['with_indicator'] }}</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Missing scoring</p>
                    <p class="mt-2 text-lg font-semibold">{{ $builderWizard['scoring']['missing'] }}</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Indicators used</p>
                    <p class="mt-2 text-lg font-semibold">{{ $builderWizard['scoring']['indicators_used'] }}</p>
                </div>
            </div>

            @if ($builderWizard['scoring']['missing'] > 0)
                <div class="mt-5 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                    <span class="font-semibold">Perlu perhatian.</span> Lengkapi indikator dan skoring sebelum mengirim instrumen ke validator.
                </div>
            @endif

            <div class="mt-5 overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Question</th>
                            <th class="px-4 py-3">Indicator</th>
                            <th class="px-4 py-3">Scale / Range</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($builderWizard['scoring']['rows'] as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ $row['question'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $row['type'] }}</p>
                                </td>
                                <td class="px-4 py-3">{{ $row['indicator'] ?: 'No indicator' }}</td>
                                <td class="px-4 py-3">{{ $row['scale'] ?: 'No scale' }} / {{ $row['score_range'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $row['status'] === 'Configured' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-800' }}">{{ $row['status'] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500">Belum ada pertanyaan yang bisa diskor. Tambahkan Likert atau pilihan berskala sebelum membuka analisis.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section id="preview" class="mt-6 scroll-mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Preview</p>
                    <h2 class="mt-1 text-xl font-semibold">Admin-only respondent preview</h2>
                    <p class="mt-1 text-sm text-slate-600">Preview ini tidak membuat SurveyResponse dan tidak menampilkan data responden.</p>
                </div>
                @if ($survey->canReceiveResponses())
                    <a href="{{ route('survey.show', ['survey' => $survey->slug]) }}" target="_blank" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Open Public Survey
                    </a>
                @endif
            </div>

            <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-5">
                <div class="mx-auto max-w-3xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-2xl font-semibold">{{ $survey->title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $survey->description ?: 'No description.' }}</p>
                    <div class="mt-6 space-y-5">
                        @forelse ($builderWizard['preview'] as $previewQuestion)
                            <article class="rounded-md border border-slate-200 p-4">
                                <div class="flex items-start gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-sm font-semibold text-white">{{ $previewQuestion['number'] }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-slate-950">
                                            {{ $previewQuestion['label'] }}
                                            @if ($previewQuestion['is_required'])
                                                <span class="text-red-600">*</span>
                                            @endif
                                        </p>
                                        @if ($previewQuestion['help_text'])
                                            <p class="mt-1 text-sm text-slate-600">{{ $previewQuestion['help_text'] }}</p>
                                        @endif

                                        @if (in_array($previewQuestion['type'], [SurveyQuestion::TYPE_SINGLE_CHOICE, SurveyQuestion::TYPE_MULTIPLE_CHOICE, SurveyQuestion::TYPE_LIKERT, SurveyQuestion::TYPE_LIKERT_MATRIX], true) && $previewQuestion['options'] !== [])
                                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                                @foreach ($previewQuestion['options'] as $option)
                                                    <label class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                                        <input type="{{ $previewQuestion['type'] === SurveyQuestion::TYPE_MULTIPLE_CHOICE ? 'checkbox' : 'radio' }}" disabled class="rounded border-slate-300 text-emerald-700">
                                                        {{ $option }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($previewQuestion['type'] === SurveyQuestion::TYPE_LONG_TEXT)
                                            <textarea disabled rows="4" placeholder="{{ $previewQuestion['placeholder'] }}" class="mt-3 block w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm"></textarea>
                                        @elseif ($previewQuestion['type'] === SurveyQuestion::TYPE_HIDDEN)
                                            <p class="mt-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">{{ $previewQuestion['placeholder'] }}</p>
                                        @elseif ($previewQuestion['type'] === SurveyQuestion::TYPE_CONSENT)
                                            <label class="mt-3 flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                                                <input type="checkbox" disabled class="mt-1 rounded border-amber-300 text-emerald-700">
                                                <span>Saya menyetujui pernyataan ini.</span>
                                            </label>
                                        @else
                                            <input disabled placeholder="{{ $previewQuestion['placeholder'] }}" class="mt-3 block w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm">
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @empty
                            <x-myriset.empty-state
                                title="Preview belum tersedia"
                                description="Preview responden akan muncul setelah minimal satu pertanyaan ditambahkan."
                            />
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section id="validasi-ahli" class="mt-6 scroll-mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Validasi Ahli</p>
                    <h2 class="mt-1 text-xl font-semibold">Expert validation readiness</h2>
                    <p class="mt-1 text-sm text-slate-600">Checklist kesiapan sebelum instrumen dikirim ke validator.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.surveys.validation.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Open Expert Validation
                    </a>
                    <a href="{{ route('admin.surveys.readability.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Readability Test
                    </a>
                    @if ($builderWizard['validation']['round_id'])
                        <a href="{{ route('admin.surveys.validation.results.show', ['survey' => $survey, 'round' => $builderWizard['validation']['round_id']]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            Open Validation Results
                        </a>
                    @endif
                </div>
            </div>

            <x-academic-output-block
                class="mt-5"
                title="Expert Validation Summary"
                description="Ringkasan aman berdasarkan putaran validasi ahli terbaru tanpa token, hash, atau kontak validator."
                :narrative="$academicNarratives['expertValidation']"
                source="Sumber: Expert Validation"
            />

            <div class="mt-5 grid gap-4 lg:grid-cols-[0.35fr_0.65fr]">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">{{ $builderWizard['validation']['complete_count'] }} of {{ count($builderWizard['validation']['items']) }} checks complete</p>
                    <p class="mt-2 text-sm text-slate-600">Round: {{ $builderWizard['validation']['round_title'] ?: 'No validation round yet' }}</p>
                    <p class="mt-1 text-sm text-slate-600">Submitted validators: {{ $builderWizard['validation']['submitted_assignments'] }}</p>
                    <p class="mt-1 text-sm text-slate-600">Validation scores: {{ $builderWizard['validation']['submitted_scores'] }}</p>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($builderWizard['validation']['items'] as $item)
                        <div class="rounded-md border {{ $item['complete'] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-4">
                            <p class="text-sm font-semibold {{ $item['complete'] ? 'text-emerald-800' : 'text-amber-900' }}">{{ $item['complete'] ? 'Ready' : 'Perlu perhatian' }}</p>
                            <p class="mt-1 text-sm text-slate-800">{{ $item['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="respons-analisis" class="mt-6 scroll-mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Respons & Analisis</p>
                    <h2 class="mt-1 text-xl font-semibold">Responses and analysis status</h2>
                    <p class="mt-1 text-sm text-slate-600">Ringkasan aman tanpa identitas responden.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.surveys.responses.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Open Responses
                    </a>
                    <a href="{{ route('admin.surveys.analysis.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Open Analysis
                    </a>
                    <a href="{{ route('admin.surveys.responses.export', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Export Responses
                    </a>
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-4">
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Responses</p>
                    <p class="mt-2 text-lg font-semibold">{{ $builderWizard['responses']['response_count'] }}</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completed</p>
                    <p class="mt-2 text-lg font-semibold">{{ $builderWizard['responses']['submitted_count'] }}</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last response</p>
                    <p class="mt-2 text-sm font-semibold">{{ $builderWizard['responses']['last_response_at'] ?: 'No submitted response yet' }}</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Analysis</p>
                    <p class="mt-2 text-sm font-semibold">{{ $builderWizard['responses']['analysis_title'] ?: 'No analysis result yet' }}</p>
                </div>
            </div>

            <x-academic-output-block
                class="mt-5"
                title="Survey Response / Analysis Summary"
                description="Narasi copy-ready dari jumlah respons dan hasil analisis terstruktur tanpa identitas responden."
                :narrative="$academicNarratives['surveyAnalysis']"
                source="Sumber: Respons & Analisis"
            />

            @if ($builderWizard['responses']['analysis_summary'] !== [])
                <div class="mt-5 grid gap-3 md:grid-cols-4">
                    @foreach ($builderWizard['responses']['analysis_summary'] as $key => $value)
                        <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label($key) }}</p>
                            <p class="mt-2 text-lg font-semibold">{{ is_scalar($value) ? $value : 'Available' }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </main>
</body>
</html>
