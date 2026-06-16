<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analysis Collection Monitoring Report - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            main { max-width: none !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-white text-slate-950 antialiased">
    <main class="mx-auto max-w-5xl px-6 py-8">
        <section class="border-b border-slate-300 pb-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Report</p>
                    <h1 class="mt-2 text-3xl font-semibold">Analysis Collection Monitoring Report</h1>
                    <p class="mt-2 text-lg font-semibold">{{ $survey->title }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $survey->project?->title ?? 'No project' }}</p>
                    <p class="mt-1 text-sm text-slate-600">Generated: {{ $generatedAt->format('d M Y H:i') }}</p>
                </div>
                <button type="button" onclick="window.print()" class="no-print rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Print</button>
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Overall Readiness</p>
            <h2 class="mt-1 text-2xl font-semibold">{{ $monitoring['readiness']['status'] }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-700">{{ $monitoring['readiness']['recommendation'] }}</p>
            <p class="mt-3 text-sm text-slate-600">Minimum met: {{ $monitoring['readiness']['minimum_met_count'] }} / {{ $monitoring['readiness']['required_count'] }}. Target met: {{ $monitoring['readiness']['target_met_count'] }} / {{ $monitoring['readiness']['required_count'] }}.</p>
        </section>

        <section class="mt-6">
            <h2 class="text-xl font-semibold">Data Source Status</h2>
            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Current</th>
                            <th class="px-4 py-3">Minimum</th>
                            <th class="px-4 py-3">Target</th>
                            <th class="px-4 py-3">Completion</th>
                            <th class="px-4 py-3">Due Date</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($monitoring['sources'] as $source)
                            <tr>
                                <td class="px-4 py-3 font-semibold">{{ $source['label'] }}</td>
                                <td class="px-4 py-3">{{ $source['current_count'] }}</td>
                                <td class="px-4 py-3">{{ $source['minimum_count'] }}</td>
                                <td class="px-4 py-3">{{ $source['target_count'] }}</td>
                                <td class="px-4 py-3">{{ $source['completion_rate'] }}%</td>
                                <td class="px-4 py-3">{{ $source['target']->due_date?->toFormattedDateString() ?? 'Not set' }}</td>
                                <td class="px-4 py-3">{{ $source['status_label'] }}</td>
                                <td class="px-4 py-3">{{ $source['target']->notes ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mt-6">
            <h2 class="text-xl font-semibold">Follow-up Notes</h2>
            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Suggested Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($monitoring['follow_up'] as $item)
                            <tr>
                                <td class="px-4 py-3 font-semibold">{{ $item['source'] }}</td>
                                <td class="px-4 py-3">{{ $item['status_label'] }}</td>
                                <td class="px-4 py-3">{{ $item['suggested_action'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-slate-500">No priority follow-up items.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-5 text-sm leading-6 text-blue-950">
            <p class="font-semibold">Privacy note</p>
            <p class="mt-1">This report shows aggregate counts only. It does not include respondent identity, validator token hashes, readability token hashes, or private public-link secrets.</p>
        </section>
    </main>
</body>
</html>
