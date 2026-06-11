<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analysis Center - ResearchHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">ResearchHub Analysis</p>
                <h1 class="mt-2 text-3xl font-semibold">Analysis Center</h1>
                <p class="mt-2 text-sm text-gray-600">{{ $survey?->title }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($survey)
                    <form method="POST" action="{{ route('admin.surveys.analysis.run', ['survey' => $survey]) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                            Run Descriptive Analysis
                        </button>
                    </form>
                    <a href="{{ route('admin.surveys.responses.index', ['survey' => $survey]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                        Responses
                    </a>
                @endif
                @if ($result)
                    <a href="{{ route('admin.analysis.export.csv', ['analysisResult' => $result]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                        Export CSV
                    </a>
                    <a href="{{ route('admin.analysis.export.markdown', ['analysisResult' => $result]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                        Export Markdown
                    </a>
                    <span class="rounded-md border border-gray-200 bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-500">
                        DOCX deferred
                    </span>
                @endif
                <a href="{{ route('filament.admin.resources.surveys.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Back to Surveys
                </a>
            </div>
        </div>

        @if (session('status'))
            <section class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                {{ str_replace('-', ' ', session('status')) }}
            </section>
        @endif

        @if ($result)
            <section class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                Dokumen ini merupakan draf akademik otomatis berbasis analisis deskriptif. Interpretasi akhir perlu diverifikasi oleh peneliti dan pembimbing sebelum digunakan dalam naskah resmi.
            </section>

            <section class="mb-6 grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Submitted</p>
                    <p class="mt-2 text-lg font-semibold">{{ $result->summary['submitted_count'] ?? 0 }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Responses</p>
                    <p class="mt-2 text-lg font-semibold">{{ $result->summary['response_count'] ?? 0 }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Questions</p>
                    <p class="mt-2 text-lg font-semibold">{{ $result->summary['analyzed_question_count'] ?? 0 }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Hidden Omitted</p>
                    <p class="mt-2 text-lg font-semibold">{{ $result->summary['hidden_question_count'] ?? 0 }}</p>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">{{ $result->title }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ $result->created_at->format('Y-m-d H:i') }} · {{ str_replace('_', ' ', $result->type) }}</p>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-800">
                        {{ $result->job?->status }}
                    </span>
                </div>

                @foreach ($result->narratives as $narrative)
                    <div class="mt-6 rounded-md border border-emerald-100 bg-emerald-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">{{ str_replace('_', ' ', $narrative->section) }}</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-emerald-950">{{ $narrative->narrative }}</p>
                    </div>
                    <div class="mt-4">
                        <label for="copy_narrative_{{ $narrative->id }}" class="block text-sm font-semibold text-gray-700">Copy-ready narrative block</label>
                        <textarea id="copy_narrative_{{ $narrative->id }}" readonly rows="7" class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm leading-6 text-gray-900 shadow-sm">{{ $narrative->narrative }}

Dokumen ini merupakan draf akademik otomatis berbasis analisis deskriptif. Interpretasi akhir perlu diverifikasi oleh peneliti dan pembimbing sebelum digunakan dalam naskah resmi.</textarea>
                    </div>
                @endforeach

                @foreach ($result->tables as $table)
                    <div class="mt-6 overflow-x-auto">
                        <h3 class="text-base font-semibold">{{ $table->title }}</h3>
                        <table class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    @foreach ($table->columns as $column)
                                        <th class="py-3 pr-4">{{ str_replace('_', ' ', $column) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
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
            </section>
        @else
            <section class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center shadow-sm">
                <h2 class="text-xl font-semibold">No analysis result yet</h2>
                <p class="mt-2 text-sm text-gray-600">Run descriptive analysis to create the first academic draft summary for this survey.</p>
            </section>
        @endif

        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Previous Results</h2>
            <div class="mt-4 divide-y divide-gray-100">
                @forelse ($results as $listedResult)
                    <a href="{{ route('admin.analysis.results.show', ['analysisResult' => $listedResult]) }}" class="flex items-center justify-between gap-4 py-3 text-sm hover:text-emerald-700">
                        <span class="font-medium">{{ $listedResult->title }}</span>
                        <span class="text-gray-500">{{ $listedResult->created_at->format('Y-m-d H:i') }}</span>
                    </a>
                @empty
                    <p class="py-4 text-sm text-gray-500">No previous analysis results.</p>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
