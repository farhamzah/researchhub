@php
    $statusClass = fn (string $status): string => match ($status) {
        'passed' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'failed' => 'border-red-200 bg-red-50 text-red-800',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };
    $severityClass = fn (string $severity): string => match ($severity) {
        'critical' => 'text-red-700',
        'warning' => 'text-amber-700',
        default => 'text-slate-500',
    };
    $overallClass = fn (string $status): string => match ($status) {
        'Ready to Send' => 'border-emerald-200 bg-emerald-50 text-emerald-950',
        'Ready with Notes' => 'border-blue-200 bg-blue-50 text-blue-950',
        'Needs Attention' => 'border-amber-200 bg-amber-50 text-amber-950',
        default => 'border-red-200 bg-red-50 text-red-950',
    };
    $sourceLabel = fn (string $value): string => str($value)->replace('_', ' ')->title()->toString();
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pre-Distribution QA Checklist - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section data-ui="myriset-page-header" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-4xl">
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Analysis</p>
                    <h1 class="mt-2 text-3xl font-semibold">Pre-Distribution QA Checklist</h1>
                    <p class="mt-2 text-lg font-semibold text-slate-800">{{ $survey->title }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $survey->project?->title ?? 'No project' }}</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Gate terakhir sebelum link PharmVR Analysis dikirim ke responden, validator, peserta readability, dan pembimbing/promotor.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($qa['nav'] as $label => $url)
                        <a href="{{ $url }}" class="{{ $label === 'Preflight QA' ? 'bg-emerald-700 text-white hover:bg-emerald-600' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }} rounded-md px-4 py-2 text-sm font-semibold shadow-sm">{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('admin.surveys.preflight.report', ['survey' => $survey]) }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Printable QA Report</a>
                    <a href="{{ route('admin.surveys.preflight.export', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Export CSV</a>
                </div>
            </div>
        </section>

        @if (session('status'))
            <section class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
                {{ str_replace('-', ' ', session('status')) }}
            </section>
        @endif

        <section class="mt-6 rounded-lg border p-6 shadow-sm {{ $overallClass($qa['overall_status']) }}">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide">Overall Readiness</p>
                    <h2 class="mt-1 text-3xl font-semibold">{{ $qa['overall_status'] }}</h2>
                    <p class="mt-2 text-sm leading-6">Marking ready does not send any links. It only records that the instruments have passed pre-distribution QA.</p>
                </div>
                @if ($qa['can_mark_ready'])
                    <form method="POST" action="{{ route('admin.surveys.preflight.mark-ready', ['survey' => $survey]) }}" class="min-w-72 rounded-lg bg-white/70 p-4">
                        @csrf
                        <label class="block text-sm">
                            <span class="font-semibold">Review notes</span>
                            <textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm"></textarea>
                        </label>
                        <button type="submit" onclick="return confirm('Mark this package as ready to send? This does not send any links.')" class="mt-3 w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Mark as Ready to Send</button>
                    </form>
                @else
                    <div class="rounded-lg bg-white/70 p-4 text-sm font-semibold">Resolve critical failures before marking ready.</div>
                @endif
            </div>
        </section>

        <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Checks</p><p class="mt-2 text-2xl font-semibold">{{ $qa['summary']['total'] }}</p></article>
            <article class="rounded-lg border border-emerald-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Passed</p><p class="mt-2 text-2xl font-semibold">{{ $qa['summary']['passed'] }}</p></article>
            <article class="rounded-lg border border-amber-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Warnings</p><p class="mt-2 text-2xl font-semibold">{{ $qa['summary']['warnings'] }}</p></article>
            <article class="rounded-lg border border-red-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-red-700">Critical Failed</p><p class="mt-2 text-2xl font-semibold">{{ $qa['summary']['critical_failed'] }}</p></article>
        </section>

        @if ($qa['critical_issues'])
            <section class="mt-6 rounded-lg border border-red-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Critical Issues First</p>
                <h2 class="mt-1 text-xl font-semibold">Must fix before sending links</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($qa['critical_issues'] as $check)
                        @include('surveys.admin.preflight.partials.check-card', ['check' => $check, 'statusClass' => $statusClass, 'severityClass' => $severityClass, 'survey' => $survey])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($qa['warnings'])
            <section class="mt-6 rounded-lg border border-amber-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Warning Items</p>
                <h2 class="mt-1 text-xl font-semibold">Recommended before sending</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($qa['warnings'] as $check)
                        @include('surveys.admin.preflight.partials.check-card', ['check' => $check, 'statusClass' => $statusClass, 'severityClass' => $severityClass, 'survey' => $survey])
                    @endforeach
                </div>
            </section>
        @endif

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Grouped Checklist</p>
            <h2 class="mt-1 text-xl font-semibold">Deterministic QA results by source</h2>
            <div class="mt-5 space-y-4">
                @foreach ($qa['grouped_checks'] as $source => $checks)
                    <details class="rounded-lg border border-slate-200 bg-slate-50 p-4" {{ $loop->first ? 'open' : '' }}>
                        <summary class="cursor-pointer font-semibold">{{ $sourceLabel($source) }} <span class="text-sm font-normal text-slate-500">({{ count($checks) }} checks)</span></summary>
                        <div class="mt-4 space-y-3">
                            @foreach ($checks as $check)
                                @include('surveys.admin.preflight.partials.check-card', ['check' => $check, 'statusClass' => $statusClass, 'severityClass' => $severityClass, 'survey' => $survey])
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    </main>
</body>
</html>
