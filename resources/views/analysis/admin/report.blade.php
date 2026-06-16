@php
    $format = fn ($value): string => $value === null ? 'N/A' : number_format((float) $value, 2);
    $label = fn (?string $value): string => $value ? str($value)->replace('_', ' ')->title()->toString() : 'N/A';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ADDIE Analysis Report - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            section { break-inside: avoid; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-white text-slate-950 antialiased">
    <main class="mx-auto max-w-6xl px-6 py-8">
        <div class="no-print mb-6 flex justify-end">
            <button onclick="window.print()" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm">Print / Save PDF</button>
        </div>

        <header class="border-b border-slate-200 pb-6">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset ADDIE Analysis Report</p>
            <h1 class="mt-2 text-3xl font-semibold">{{ $survey->title }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $survey->project?->title ?? 'No project' }}</p>
            <p class="mt-1 text-xs text-slate-500">Generated {{ $generatedAt->format('Y-m-d H:i') }}</p>
        </header>

        <section class="mt-6 grid gap-4 md:grid-cols-4">
            @foreach ($dashboard['summary_cards'] as $card)
                <div class="rounded-lg border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-2 text-xl font-semibold">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </section>

        <section class="mt-8">
            <h2 class="text-xl font-semibold">Readiness for Design</h2>
            <p class="mt-2 text-sm text-slate-700">{{ $dashboard['readiness']['status'] }}</p>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
                @foreach ($dashboard['readiness']['criteria'] as $criterion)
                    <li>{{ $criterion['label'] }}: {{ $criterion['complete'] ? 'Yes' : 'No' }}</li>
                @endforeach
            </ul>
        </section>

        <section class="mt-8">
            <h2 class="text-xl font-semibold">Survey Response Summary</h2>
            <table class="mt-4 min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">#</th>
                        <th class="py-2 pr-4">Question</th>
                        <th class="py-2 pr-4">Type</th>
                        <th class="py-2 pr-4">Responses</th>
                        <th class="py-2 pr-4">Summary</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($dashboard['response_summary'] as $row)
                        <tr>
                            <td class="py-2 pr-4">{{ $row['number'] }}</td>
                            <td class="py-2 pr-4">{{ $row['label'] }}</td>
                            <td class="py-2 pr-4">{{ $label($row['type']) }}</td>
                            <td class="py-2 pr-4">{{ $row['answered_count'] }}</td>
                            <td class="py-2 pr-4">{{ $row['summary_text'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="mt-8 grid gap-6 md:grid-cols-2">
            <div>
                <h2 class="text-xl font-semibold">Expert Validation Summary</h2>
                @if ($dashboard['validation']['has_submissions'])
                    <p class="mt-2 text-sm">Average {{ $format($dashboard['validation']['average_score']) }} - {{ $dashboard['validation']['category'] }}</p>
                    <p class="text-sm">Submitted {{ $dashboard['validation']['submitted_count'] }} of {{ $dashboard['validation']['assigned_count'] }}</p>
                @else
                    <p class="mt-2 text-sm text-slate-600">{{ $dashboard['validation']['empty_state'] }}</p>
                @endif
            </div>
            <div>
                <h2 class="text-xl font-semibold">Readability Summary</h2>
                @if ($dashboard['readability']['has_submissions'])
                    <p class="mt-2 text-sm">Average {{ $format($dashboard['readability']['average_score']) }} - {{ $dashboard['readability']['category'] }}</p>
                    <p class="text-sm">Submitted {{ $dashboard['readability']['submitted_count'] }} of {{ $dashboard['readability']['participant_count'] }}</p>
                @else
                    <p class="mt-2 text-sm text-slate-600">{{ $dashboard['readability']['empty_state'] }}</p>
                @endif
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-xl font-semibold">Priority Rankings</h2>
            <div class="mt-4 grid gap-6 md:grid-cols-2">
                <div>
                    <h3 class="font-semibold">Features</h3>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm">
                        @forelse ($dashboard['priority']['features'] as $row)
                            <li>{{ $row['label'] }} - {{ $row['count'] }} selected ({{ $format($row['percentage']) }}%)</li>
                        @empty
                            <li>No matching feature priority data.</li>
                        @endforelse
                    </ol>
                </div>
                <div>
                    <h3 class="font-semibold">Scenes</h3>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm">
                        @forelse ($dashboard['priority']['scenes'] as $row)
                            <li>{{ $row['label'] }} - {{ $row['count'] }} selected ({{ $format($row['percentage']) }}%)</li>
                        @empty
                            <li>No matching scene priority data.</li>
                        @endforelse
                    </ol>
                </div>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-xl font-semibold">Synthesis Matrix</h2>
            <table class="mt-4 min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">Source</th>
                        <th class="py-2 pr-4">Theme</th>
                        <th class="py-2 pr-4">Finding</th>
                        <th class="py-2 pr-4">Evidence</th>
                        <th class="py-2 pr-4">Priority</th>
                        <th class="py-2 pr-4">Design Implication</th>
                        <th class="py-2 pr-4">Development Decision</th>
                        <th class="py-2 pr-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($dashboard['synthesis_items'] as $item)
                        <tr>
                            <td class="py-2 pr-4">{{ $synthesisOptions['sources'][$item->source_type] ?? $label($item->source_type) }}</td>
                            <td class="py-2 pr-4">{{ $synthesisOptions['themes'][$item->theme] ?? $label($item->theme) }}</td>
                            <td class="py-2 pr-4">{{ $item->finding }}</td>
                            <td class="py-2 pr-4">{{ $item->evidence_metric }} {{ $item->evidence_summary }}</td>
                            <td class="py-2 pr-4">{{ $synthesisOptions['priorities'][$item->priority_level] ?? $label($item->priority_level) }}</td>
                            <td class="py-2 pr-4">{{ $item->design_implication }}</td>
                            <td class="py-2 pr-4">{{ $item->development_decision }}</td>
                            <td class="py-2 pr-4">{{ $synthesisOptions['statuses'][$item->status] ?? $label($item->status) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-slate-500">No synthesis matrix items yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
