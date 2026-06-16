@php
    use App\Models\AnalysisCollectionTarget;

    $statusClass = fn (string $status): string => match ($status) {
        AnalysisCollectionTarget::STATUS_TARGET_MET => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        AnalysisCollectionTarget::STATUS_MINIMUM_MET => 'border-amber-200 bg-amber-50 text-amber-900',
        AnalysisCollectionTarget::STATUS_COLLECTING => 'border-blue-200 bg-blue-50 text-blue-800',
        AnalysisCollectionTarget::STATUS_OVERDUE => 'border-red-200 bg-red-50 text-red-800',
        AnalysisCollectionTarget::STATUS_NOT_APPLICABLE => 'border-slate-200 bg-slate-100 text-slate-700',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };
    $readinessClass = fn (string $status): string => match ($status) {
        'Fully Ready', 'Minimum Ready' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'Partially Ready' => 'border-amber-200 bg-amber-50 text-amber-950',
        default => 'border-red-200 bg-red-50 text-red-900',
    };
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analysis Data Collection Monitoring - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section data-ui="myriset-page-header" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-4xl">
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Analysis</p>
                    <h1 class="mt-2 text-3xl font-semibold">Analysis Data Collection Monitoring</h1>
                    <p class="mt-2 text-lg font-semibold text-slate-800">{{ $survey->title }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $survey->project?->title ?? 'No project' }}</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Pantau minimum, target, deadline, response rate, dan kesiapan data Analysis sebelum masuk ke tahap Design PharmVR.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($monitoring['nav'] as $label => $url)
                        <a href="{{ $url }}" class="{{ $label === 'Collection Monitoring' ? 'bg-emerald-700 text-white hover:bg-emerald-600' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }} rounded-md px-4 py-2 text-sm font-semibold shadow-sm">{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('admin.surveys.collection-monitoring.report', ['survey' => $survey]) }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Printable Report</a>
                    <a href="{{ route('admin.surveys.collection-monitoring.export', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Export CSV</a>
                    <a href="{{ route('admin.surveys.analysis-package.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Analysis Package</a>
                </div>
            </div>
        </section>

        @if (session('status'))
            <section class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
                {{ str_replace('-', ' ', session('status')) }}
            </section>
        @endif

        @if ($errors->any())
            <section class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Please review the target settings.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($monitoring['summary_cards'] as $card)
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-3 text-2xl font-semibold text-slate-950">{{ $card['value'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="mt-6 rounded-lg border p-6 shadow-sm {{ $readinessClass($monitoring['readiness']['status']) }}">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide">Overall Analysis Readiness</p>
                    <h2 class="mt-1 text-2xl font-semibold">{{ $monitoring['readiness']['status'] }}</h2>
                    <p class="mt-2 text-sm leading-6">{{ $monitoring['readiness']['recommendation'] }}</p>
                </div>
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-md bg-white/70 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Minimum Ready</dt>
                        <dd class="mt-1 text-xl font-semibold">{{ $monitoring['readiness']['minimum_met_count'] }} / {{ $monitoring['readiness']['required_count'] }}</dd>
                    </div>
                    <div class="rounded-md bg-white/70 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fully Ready</dt>
                        <dd class="mt-1 text-xl font-semibold">{{ $monitoring['readiness']['target_met_count'] }} / {{ $monitoring['readiness']['required_count'] }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Data Sources</p>
                    <h2 class="mt-1 text-xl font-semibold">Minimum, target, dan status pengumpulan</h2>
                    <p class="mt-1 text-sm text-slate-600">Angka yang ditampilkan adalah aggregate count. Identitas responden, token, dan hash tidak ditampilkan.</p>
                </div>
            </div>

            <div class="mt-5 space-y-5">
                @foreach ($monitoring['sources'] as $source)
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-slate-950">{{ $source['label'] }}</h3>
                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass($source['status']) }}">{{ $source['status_label'] }}</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">{{ $source['metric_label'] }}: <span class="font-semibold text-slate-950">{{ $source['current_count'] }}</span></p>
                                @if ($source['instrument'])
                                    <p class="mt-1 text-xs text-slate-500">Instrument: {{ $source['instrument']->title }}</p>
                                @elseif (! $source['is_applicable'])
                                    <p class="mt-1 text-xs text-slate-500">Instrument belum dibuat untuk sumber data ini.</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if ($source['link_route'])
                                    <a href="{{ $source['link_route'] }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Open Source</a>
                                @endif
                                @if ($source['public_route'])
                                    <a href="{{ $source['public_route'] }}" target="_blank" class="rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">Public Link</a>
                                @endif
                                <a href="{{ route('admin.surveys.distribution.index', ['survey' => $survey]) }}" class="rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Distribution Center</a>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 lg:grid-cols-3">
                            <div class="lg:col-span-2">
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-md bg-white p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Current</p>
                                        <p class="mt-1 text-xl font-semibold">{{ $source['current_count'] }}</p>
                                    </div>
                                    <div class="rounded-md bg-white p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Minimum</p>
                                        <p class="mt-1 text-xl font-semibold">{{ $source['minimum_count'] }}</p>
                                    </div>
                                    <div class="rounded-md bg-white p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Target</p>
                                        <p class="mt-1 text-xl font-semibold">{{ $source['target_count'] }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <div>
                                        <div class="flex justify-between text-xs font-semibold text-slate-600">
                                            <span>{{ $source['current_count'] }} / {{ $source['minimum_count'] }} minimum</span>
                                            <span>{{ $source['minimum_percent'] }}%</span>
                                        </div>
                                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-200">
                                            <div class="h-full rounded-full bg-amber-500" style="width: {{ $source['minimum_percent'] }}%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-xs font-semibold text-slate-600">
                                            <span>{{ $source['current_count'] }} / {{ $source['target_count'] }} target</span>
                                            <span>{{ $source['target_percent'] }}%</span>
                                        </div>
                                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-200">
                                            <div class="h-full rounded-full bg-emerald-600" style="width: {{ $source['target_percent'] }}%"></div>
                                        </div>
                                    </div>
                                </div>

                                @if ($source['distribution'])
                                    <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-5">
                                        <div><dt class="text-xs text-slate-500">Sent</dt><dd class="font-semibold">{{ $source['distribution']['sent_manually_count'] }}</dd></div>
                                        <div><dt class="text-xs text-slate-500">Link Ready</dt><dd class="font-semibold">{{ $source['distribution']['link_ready_count'] }}</dd></div>
                                        <div><dt class="text-xs text-slate-500">Submitted</dt><dd class="font-semibold">{{ $source['distribution']['submitted_count'] }}</dd></div>
                                        <div><dt class="text-xs text-slate-500">Pending</dt><dd class="font-semibold">{{ $source['distribution']['pending_count'] }}</dd></div>
                                        <div><dt class="text-xs text-slate-500">Revoked</dt><dd class="font-semibold">{{ $source['distribution']['revoked_count'] }}</dd></div>
                                    </dl>
                                @endif

                                @if (! empty($source['extra']))
                                    <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-4">
                                        @foreach ($source['extra'] as $label => $value)
                                            <div><dt class="text-xs text-slate-500">{{ str($label)->replace('_', ' ')->title() }}</dt><dd class="font-semibold">{{ $value }}</dd></div>
                                        @endforeach
                                    </dl>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('admin.surveys.collection-monitoring.targets.update', ['survey' => $survey, 'target' => $source['target']]) }}" class="rounded-lg border border-slate-200 bg-white p-4">
                                @csrf
                                @method('PUT')
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Target Configuration</p>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                                    <label class="block text-sm">
                                        <span class="font-semibold text-slate-700">Minimum count</span>
                                        <input name="minimum_count" type="number" min="0" value="{{ old('minimum_count', $source['target']->minimum_count) }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                    </label>
                                    <label class="block text-sm">
                                        <span class="font-semibold text-slate-700">Target count</span>
                                        <input name="target_count" type="number" min="0" value="{{ old('target_count', $source['target']->target_count) }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                    </label>
                                    <label class="block text-sm">
                                        <span class="font-semibold text-slate-700">Due date</span>
                                        <input name="due_date" type="date" value="{{ old('due_date', $source['target']->due_date?->toDateString()) }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                    </label>
                                    <label class="block text-sm">
                                        <span class="font-semibold text-slate-700">Notes</span>
                                        <textarea name="notes" rows="3" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('notes', $source['target']->notes) }}</textarea>
                                    </label>
                                </div>
                                <button type="submit" class="mt-3 w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Save Target</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Need Follow-up</p>
                    <h2 class="mt-1 text-xl font-semibold">Sumber data yang perlu tindakan</h2>
                </div>
                <a href="{{ route('admin.surveys.distribution.index', ['survey' => $survey]) }}" class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Open Distribution Center</a>
            </div>

            <div class="mt-5 overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Current</th>
                            <th class="px-4 py-3">Minimum / Target</th>
                            <th class="px-4 py-3">Due Date</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Suggested Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($monitoring['follow_up'] as $item)
                            <tr>
                                <td class="px-4 py-3 font-semibold">{{ $item['source'] }}</td>
                                <td class="px-4 py-3">{{ $item['current_count'] }}</td>
                                <td class="px-4 py-3">{{ $item['minimum_count'] }} / {{ $item['target_count'] }}</td>
                                <td class="px-4 py-3">{{ $item['due_date']?->toFormattedDateString() ?? 'Not set' }}</td>
                                <td class="px-4 py-3">{{ $item['status_label'] }}</td>
                                <td class="px-4 py-3">{{ $item['suggested_action'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Tidak ada follow-up prioritas. Semua sumber data minimal sudah terpenuhi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
