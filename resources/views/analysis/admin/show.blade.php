@php
    use App\Models\AnalysisSynthesisItem;

    $format = fn ($value): string => $value === null ? 'N/A' : number_format((float) $value, 2);
    $label = fn (?string $value): string => $value ? str($value)->replace('_', ' ')->title()->toString() : 'N/A';
    $dashboard = $dashboard ?? null;
    $synthesisItems = $dashboard['synthesis_items'] ?? collect();
    $filters = [
        'source_type' => request('source_type'),
        'theme' => request('theme'),
        'priority_level' => request('priority_level'),
        'mapped_module' => request('mapped_module'),
    ];
    $filteredSynthesisItems = $synthesisItems
        ->when($filters['source_type'], fn ($items, $value) => $items->where('source_type', $value))
        ->when($filters['theme'], fn ($items, $value) => $items->where('theme', $value))
        ->when($filters['priority_level'], fn ($items, $value) => $items->where('priority_level', $value))
        ->when($filters['mapped_module'], fn ($items, $value) => $items->where('mapped_module', $value));
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ADDIE Analysis Dashboard - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section data-ui="myriset-page-header" class="mb-8 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-4xl">
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Analysis</p>
                    <h1 class="mt-2 text-3xl font-semibold">ADDIE Analysis Dashboard</h1>
                    <p class="mt-2 text-lg font-semibold text-slate-800">{{ $survey?->title }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $survey?->project?->title ?? 'No project' }}</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Dashboard ini menyatukan respons survey utama, validasi ahli, uji keterbacaan, dan matriks sintesis agar output tahap Analysis ADDIE dapat diterjemahkan menjadi keputusan Design dan Development PharmVR.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($survey)
                        <a href="{{ route('admin.surveys.builder.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Builder</a>
                        <a href="{{ route('admin.surveys.validation.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Validation</a>
                        <a href="{{ route('admin.surveys.readability.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Readability</a>
                        <a href="{{ route('admin.surveys.distribution.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Distribution Center</a>
                        <a href="{{ route('admin.surveys.collection-monitoring.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Collection Monitoring</a>
                        <a href="{{ route('admin.surveys.analysis-package.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Analysis Package</a>
                        <a href="{{ route('admin.surveys.analysis.report', ['survey' => $survey]) }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Printable Report</a>
                        <a href="{{ route('filament.admin.resources.surveys.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back to Surveys</a>
                    @endif
                </div>
            </div>
        </section>

        @if (session('status'))
            <section class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
                {{ str_replace('-', ' ', session('status')) }}
            </section>
        @endif

        @if ($errors->any())
            <section class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Please review the analysis form.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($dashboard)
            <section class="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                @foreach ($dashboard['summary_cards'] as $card)
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold">{{ $card['value'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Readiness for Design</p>
                        <h2 class="mt-1 text-xl font-semibold">{{ $dashboard['readiness']['status'] }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ $dashboard['readiness']['complete_count'] }} of {{ count($dashboard['readiness']['criteria']) }} criteria complete.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.surveys.analysis.generate-synthesis', ['survey' => $survey]) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Generate Draft Synthesis</button>
                    </form>
                </div>
                <div class="mt-5 grid gap-3 md:grid-cols-4">
                    @foreach ($dashboard['readiness']['criteria'] as $criterion)
                        <div class="rounded-md border p-4 {{ $criterion['complete'] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
                            <p class="text-xs font-semibold uppercase tracking-wide {{ $criterion['complete'] ? 'text-emerald-700' : 'text-amber-800' }}">{{ $criterion['complete'] ? 'Complete' : 'Needed' }}</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $criterion['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">ADDIE Analysis Instruments</p>
                        <h2 class="mt-1 text-xl font-semibold">Triangulasi Instrumen Analysis PharmVR</h2>
                        <p class="mt-1 text-sm text-slate-600">Gunakan survey mahasiswa, kuesioner dosen, dan wawancara praktisi untuk memperkuat evidence sebelum tahap Design.</p>
                    </div>
                </div>
                <div class="mt-5 grid gap-4 lg:grid-cols-3">
                    @foreach ($dashboard['analysis_instruments'] as $instrumentKey => $instrument)
                        <article class="rounded-lg border {{ $instrument['exists'] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide {{ $instrument['exists'] ? 'text-emerald-700' : 'text-amber-800' }}">{{ $instrument['exists'] ? 'Exists' : 'Not created' }}</p>
                                    <h3 class="mt-1 text-lg font-semibold text-slate-950">{{ $instrument['label'] }}</h3>
                                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ $instrument['description'] }}</p>
                                </div>
                            </div>

                            @if ($instrument['exists'])
                                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div class="rounded-md bg-white/70 p-3">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Responses</dt>
                                        <dd class="mt-1 font-semibold">{{ $instrument['submitted_response_count'] }} submitted</dd>
                                    </div>
                                    <div class="rounded-md bg-white/70 p-3">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Public</dt>
                                        <dd class="mt-1 font-semibold">{{ $instrument['can_receive_responses'] ? 'Open' : 'Not open' }}</dd>
                                    </div>
                                </dl>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <a href="{{ route('admin.surveys.builder.index', ['survey' => $instrument['survey']]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Open Builder</a>
                                    <a href="{{ route('admin.surveys.analysis.index', ['survey' => $instrument['survey']]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Response Summary</a>
                                    @if ($instrument['can_receive_responses'])
                                        <a href="{{ route('survey.show', ['survey' => $instrument['survey']->slug]) }}" target="_blank" class="rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">Public Form</a>
                                    @endif
                                </div>
                            @elseif ($instrument['can_create'] && $instrument['create_route'])
                                <form method="POST" action="{{ route($instrument['create_route'], ['survey' => $survey]) }}" class="mt-4">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                                        {{ $instrumentKey === 'lecturer' ? 'Create Lecturer Questionnaire' : 'Create Practitioner Interview Form' }}
                                    </button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
                <p class="mt-4 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-900">Privacy note: lecturer and practitioner instruments allow initials. Company/industry names may be omitted when confidential.</p>
            </section>

            <section class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Survey Response Summary</p>
                        <h2 class="mt-1 text-xl font-semibold">Ringkasan Respons Survey Utama</h2>
                    </div>
                    <a href="{{ route('admin.surveys.responses.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Open Responses</a>
                </div>
                <div class="mt-5 overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Question</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Responses</th>
                                <th class="px-4 py-3">Summary</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($dashboard['response_summary'] as $row)
                                <tr>
                                    <td class="px-4 py-3">{{ $row['number'] }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ $row['label'] }}</td>
                                    <td class="px-4 py-3">{{ $label($row['type']) }}</td>
                                    <td class="px-4 py-3">{{ $row['answered_count'] }}</td>
                                    <td class="px-4 py-3">{{ $row['summary_text'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-500">Belum ada pertanyaan survey untuk diringkas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mb-6 grid gap-6 xl:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold">Prioritas Fitur PharmVR</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($dashboard['priority']['features'] as $row)
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="font-semibold">{{ $row['label'] }}</p>
                                    <p class="text-sm text-slate-600">{{ $row['count'] }} / {{ $format($row['percentage']) }}%</p>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['question'] }}</p>
                            </div>
                        @empty
                            <p class="rounded-md border border-dashed border-slate-300 p-5 text-sm text-slate-500">Belum ada data prioritas fitur yang cocok dengan keyword fitur/feature.</p>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold">Prioritas Scene PharmVR</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($dashboard['priority']['scenes'] as $row)
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="font-semibold">{{ $row['label'] }}</p>
                                    <p class="text-sm text-slate-600">{{ $row['count'] }} / {{ $format($row['percentage']) }}%</p>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['question'] }}</p>
                            </div>
                        @empty
                            <p class="rounded-md border border-dashed border-slate-300 p-5 text-sm text-slate-500">Belum ada data prioritas scene yang cocok dengan keyword scene/VR/CPOB/GMP.</p>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold">Top Learning Difficulties</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($dashboard['priority']['difficulties'] as $row)
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                                <p class="font-semibold">{{ $row['label'] }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $row['metric'] }} - {{ $row['summary'] }}</p>
                            </div>
                        @empty
                            <p class="rounded-md border border-dashed border-slate-300 p-5 text-sm text-slate-500">Belum ada item kesulitan yang dapat diringkas.</p>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold">Technology Readiness</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($dashboard['priority']['technology'] as $row)
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                                <p class="font-semibold">{{ $row['label'] }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $row['metric'] }} - {{ $row['summary'] }}</p>
                            </div>
                        @empty
                            <p class="rounded-md border border-dashed border-slate-300 p-5 text-sm text-slate-500">Belum ada item kesiapan teknologi yang dapat diringkas.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="mb-6 grid gap-6 xl:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Expert Validation</p>
                            <h2 class="mt-1 text-xl font-semibold">Ringkasan Validasi Ahli</h2>
                        </div>
                        @if ($dashboard['validation']['round'])
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.surveys.validation.results.show', ['survey' => $survey, 'round' => $dashboard['validation']['round']]) }}" class="rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">Full Results</a>
                                <a href="{{ route('admin.surveys.validation.report', ['survey' => $survey, 'round' => $dashboard['validation']['round']]) }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Printable</a>
                            </div>
                        @endif
                    </div>
                    @if ($dashboard['validation']['has_submissions'])
                        <div class="mt-5 grid gap-3 md:grid-cols-2">
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">Rounds</p><p class="text-lg font-semibold">{{ $dashboard['validation']['round_count'] }}</p></div>
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">Submitted</p><p class="text-lg font-semibold">{{ $dashboard['validation']['submitted_count'] }} / {{ $dashboard['validation']['assigned_count'] }}</p></div>
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">Average</p><p class="text-lg font-semibold">{{ $format($dashboard['validation']['average_score']) }}</p></div>
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">Category</p><p class="text-lg font-semibold">{{ $dashboard['validation']['category'] }}</p></div>
                        </div>
                        <div class="mt-5">
                            <h3 class="text-sm font-semibold">Aspect Summary</h3>
                            <div class="mt-3 grid gap-2 md:grid-cols-2">
                                @foreach ($dashboard['validation']['aspect_summary'] as $aspect)
                                    <p class="rounded-md bg-slate-50 px-3 py-2 text-sm">{{ $aspect['label'] }}: {{ $format($aspect['average_score']) }}</p>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="mt-5 rounded-md border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-600">{{ $dashboard['validation']['empty_state'] }}</p>
                    @endif
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Readability Test</p>
                            <h2 class="mt-1 text-xl font-semibold">Ringkasan Uji Keterbacaan</h2>
                        </div>
                        @if ($dashboard['readability']['round'])
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.surveys.readability.index', ['survey' => $survey]) }}" class="rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">Readability</a>
                                <a href="{{ route('admin.surveys.readability.report', ['survey' => $survey, 'round' => $dashboard['readability']['round']]) }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Printable</a>
                            </div>
                        @endif
                    </div>
                    @if ($dashboard['readability']['has_submissions'])
                        <div class="mt-5 grid gap-3 md:grid-cols-2">
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">Rounds</p><p class="text-lg font-semibold">{{ $dashboard['readability']['round_count'] }}</p></div>
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">Submitted</p><p class="text-lg font-semibold">{{ $dashboard['readability']['submitted_count'] }} / {{ $dashboard['readability']['participant_count'] }}</p></div>
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">Average</p><p class="text-lg font-semibold">{{ $format($dashboard['readability']['average_score']) }}</p></div>
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">Category</p><p class="text-lg font-semibold">{{ $dashboard['readability']['category'] }}</p></div>
                        </div>
                        <div class="mt-5">
                            <h3 class="text-sm font-semibold">Top Issue Types</h3>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse ($dashboard['readability']['issue_type_counts'] as $issue => $count)
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $label($issue) }}: {{ $count }}</span>
                                @empty
                                    <span class="text-sm text-slate-500">No issue types submitted.</span>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <p class="mt-5 rounded-md border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-600">{{ $dashboard['readability']['empty_state'] }}</p>
                    @endif
                </div>
            </section>

            <section class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Matriks Sintesis Kebutuhan PharmVR</p>
                        <h2 class="mt-1 text-xl font-semibold">Synthesis Matrix</h2>
                    </div>
                    <form method="GET" action="{{ route('admin.surveys.analysis.index', ['survey' => $survey]) }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                        <select name="source_type" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">All sources</option>
                            @foreach ($synthesisOptions['sources'] as $value => $optionLabel)
                                <option value="{{ $value }}" @selected($filters['source_type'] === $value)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                        <select name="theme" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">All themes</option>
                            @foreach ($synthesisOptions['themes'] as $value => $optionLabel)
                                <option value="{{ $value }}" @selected($filters['theme'] === $value)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                        <select name="priority_level" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">All priorities</option>
                            @foreach ($synthesisOptions['priorities'] as $value => $optionLabel)
                                <option value="{{ $value }}" @selected($filters['priority_level'] === $value)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                        <select name="mapped_module" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">All modules</option>
                            @foreach ($synthesisOptions['modules'] as $module)
                                <option value="{{ $module }}" @selected($filters['mapped_module'] === $module)>{{ $module }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Filter</button>
                    </form>
                </div>

                <details class="mt-5 rounded-lg border border-slate-200 bg-slate-50">
                    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-800">Add synthesis item manually</summary>
                    <form method="POST" action="{{ route('admin.surveys.analysis.synthesis-items.store', ['survey' => $survey]) }}" class="grid gap-3 border-t border-slate-200 p-4 lg:grid-cols-3">
                        @csrf
                        @include('analysis.admin.partials.synthesis-form-fields', ['item' => null])
                        <button type="submit" class="lg:col-span-3 rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Add Synthesis Item</button>
                    </form>
                </details>

                <div class="mt-5 overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Source</th>
                                <th class="px-4 py-3">Theme</th>
                                <th class="px-4 py-3">Finding</th>
                                <th class="px-4 py-3">Evidence</th>
                                <th class="px-4 py-3">Priority</th>
                                <th class="px-4 py-3">Design / Development</th>
                                <th class="px-4 py-3">Module</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($filteredSynthesisItems as $item)
                                <tr>
                                    <td class="px-4 py-3">{{ $synthesisOptions['sources'][$item->source_type] ?? $label($item->source_type) }}<p class="text-xs text-slate-500">{{ $item->source_label }}</p></td>
                                    <td class="px-4 py-3">{{ $synthesisOptions['themes'][$item->theme] ?? $label($item->theme) }}</td>
                                    <td class="px-4 py-3 max-w-md">{{ $item->finding }}</td>
                                    <td class="px-4 py-3 max-w-sm">{{ $item->evidence_metric }}<p class="text-xs text-slate-500">{{ $item->evidence_summary }}</p></td>
                                    <td class="px-4 py-3">{{ $synthesisOptions['priorities'][$item->priority_level] ?? $label($item->priority_level) }}</td>
                                    <td class="px-4 py-3 max-w-md"><p>{{ $item->design_implication ?: 'No design implication.' }}</p><p class="mt-1 text-xs text-slate-500">{{ $item->development_decision }}</p></td>
                                    <td class="px-4 py-3">{{ $item->mapped_module ?: 'N/A' }}</td>
                                    <td class="px-4 py-3">{{ $synthesisOptions['statuses'][$item->status] ?? $label($item->status) }}</td>
                                    <td class="px-4 py-3">
                                        <details>
                                            <summary class="cursor-pointer text-xs font-semibold text-emerald-700">Edit</summary>
                                            <form method="POST" action="{{ route('admin.surveys.analysis.synthesis-items.update', ['survey' => $survey, 'synthesisItem' => $item]) }}" class="mt-3 grid w-[720px] max-w-[80vw] gap-3 rounded-md border border-slate-200 bg-slate-50 p-4 lg:grid-cols-3">
                                                @csrf
                                                @method('PUT')
                                                @include('analysis.admin.partials.synthesis-form-fields', ['item' => $item])
                                                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Save</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.surveys.analysis.synthesis-items.delete', ['survey' => $survey, 'synthesisItem' => $item]) }}" class="mt-2">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" onclick="return confirm('Delete this synthesis item?')" class="rounded-md border border-red-300 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-50">Delete</button>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-slate-500">Belum ada synthesis item. Tambahkan manual atau gunakan Generate Draft Synthesis.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Descriptive Analysis</p>
                        <h2 class="mt-1 text-xl font-semibold">Existing Analysis Center</h2>
                        <p class="mt-1 text-sm text-slate-600">Bagian ini mempertahankan workflow descriptive analysis dan export draft akademik yang sudah ada.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.surveys.analysis.run', ['survey' => $survey]) }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Run Descriptive Analysis</button>
                        </form>
                        @if ($result)
                            <a href="{{ route('admin.analysis.export.csv', ['analysisResult' => $result]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Export CSV Table Data</a>
                            <a href="{{ route('admin.analysis.export.markdown', ['analysisResult' => $result]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Export Markdown Draft</a>
                            <a href="{{ route('admin.analysis.export.docx', ['analysisResult' => $result]) }}" class="rounded-md border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-100">Export DOCX Descriptive Draft</a>
                        @endif
                    </div>
                </div>

                @if ($result)
                    <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm">
                        <p class="text-sm font-semibold text-amber-900">Descriptive Draft Notice</p>
                        <p class="mt-1 text-sm leading-6 text-amber-800">
                            All exports (CSV, Markdown, DOCX) are <strong>descriptive academic drafts generated automatically</strong>.
                            They do not include inferential conclusions and must be reviewed by the researcher and supervisor before use in any official academic document.
                        </p>
                    </div>
                    <div class="mt-5 grid gap-4 md:grid-cols-4">
                        <div class="rounded-md border border-slate-100 bg-slate-50 p-4"><p class="text-xs text-slate-500">Submitted</p><p class="text-lg font-semibold">{{ $result->summary['submitted_count'] ?? 0 }}</p></div>
                        <div class="rounded-md border border-slate-100 bg-slate-50 p-4"><p class="text-xs text-slate-500">Responses</p><p class="text-lg font-semibold">{{ $result->summary['response_count'] ?? 0 }}</p></div>
                        <div class="rounded-md border border-slate-100 bg-slate-50 p-4"><p class="text-xs text-slate-500">Questions Analyzed</p><p class="text-lg font-semibold">{{ $result->summary['analyzed_question_count'] ?? 0 }}</p></div>
                        <div class="rounded-md border border-slate-100 bg-slate-50 p-4"><p class="text-xs text-slate-500">Hidden Questions Excluded</p><p class="text-lg font-semibold">{{ $result->summary['hidden_question_count'] ?? 0 }}</p></div>
                    </div>
                    @foreach ($result->narratives as $narrative)
                        <div class="mt-5 rounded-md border border-emerald-100 bg-emerald-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">{{ str_replace('_', ' ', $narrative->section) }}</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-emerald-950">{{ $narrative->narrative }}</p>
                        </div>
                        <div class="mt-4">
                            <label for="copy_narrative_{{ $narrative->id }}" class="block text-sm font-semibold text-slate-700">Copy-ready academic narrative - paste directly into thesis draft</label>
                            <textarea id="copy_narrative_{{ $narrative->id }}" readonly rows="7" class="mt-2 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm leading-6 text-slate-900 shadow-sm">{{ $narrative->narrative }}

{{ \App\Modules\Analysis\Services\AcademicDraftBuilder::DISCLAIMER }}</textarea>
                        </div>
                    @endforeach
                    @foreach ($result->tables as $table)
                        <div class="mt-6 overflow-x-auto">
                            <h3 class="text-base font-semibold">{{ $table->title }}</h3>
                            <table class="mt-3 min-w-full divide-y divide-slate-200 text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        @foreach ($table->columns as $column)
                                            <th class="py-3 pr-4">{{ str_replace('_', ' ', $column) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($table->rows as $row)
                                        <tr>
                                            @foreach ($table->columns as $column)
                                                <td class="py-3 pr-4">{{ $row[$column] ?? '-' }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                @else
                    <p class="mt-5 rounded-md border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">No descriptive analysis result yet. Run descriptive analysis to create an academic draft summary.</p>
                @endif
            </section>
        @endif
    </main>
</body>
</html>
