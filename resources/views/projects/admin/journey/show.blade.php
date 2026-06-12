@php
    use App\Modules\Projects\Services\ProjectResearchJourneyService;

    $statusClass = fn (string $status): string => match ($status) {
        ProjectResearchJourneyService::STATUS_COMPLETED => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        ProjectResearchJourneyService::STATUS_IN_PROGRESS => 'bg-blue-50 text-blue-800 border-blue-200',
        ProjectResearchJourneyService::STATUS_NEEDS_ATTENTION => 'bg-amber-50 text-amber-900 border-amber-200',
        ProjectResearchJourneyService::STATUS_BLOCKED => 'bg-red-50 text-red-800 border-red-200',
        default => 'bg-slate-50 text-slate-700 border-slate-200',
    };
    $barClass = fn (string $status): string => match ($status) {
        ProjectResearchJourneyService::STATUS_COMPLETED => 'bg-emerald-600',
        ProjectResearchJourneyService::STATUS_IN_PROGRESS => 'bg-blue-600',
        ProjectResearchJourneyService::STATUS_NEEDS_ATTENTION => 'bg-amber-500',
        ProjectResearchJourneyService::STATUS_BLOCKED => 'bg-red-600',
        default => 'bg-slate-300',
    };
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alur Riset - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">MyRiset Research Journey</p>
                <h1 class="mt-2 text-3xl font-semibold">Alur Riset</h1>
                <p class="mt-2 text-sm text-slate-600">{{ $project->title }} · {{ $journey['project']['status'] }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('filament.admin.resources.projects.research-projects.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Kembali ke Projects
                </a>
                <a href="{{ url('/admin') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Dashboard
                </a>
            </div>
        </div>

        <section class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-6 lg:grid-cols-[1fr_22rem] lg:items-end">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-semibold">Langkah berikutnya</h2>
                        <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass($journey['next_step']['status']) }}">
                            {{ $journey['next_step']['status_label'] }}
                        </span>
                    </div>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">{{ $journey['next_step']['description'] }}</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ $journey['next_step']['action_url'] }}" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-600">
                            {{ $journey['next_step']['action_label'] }}
                        </a>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Progress Alur Riset</p>
                            <p class="mt-2 text-4xl font-semibold text-blue-700">{{ $journey['progress_percentage'] }}%</p>
                        </div>
                        <div class="text-right text-sm text-slate-600">
                            <p>{{ $journey['completed_count'] }} / {{ $journey['steps']->count() }} selesai</p>
                            <p>{{ $journey['attention_count'] }} perlu perhatian</p>
                        </div>
                    </div>
                    <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-blue-700" style="width: {{ $journey['progress_percentage'] }}%"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Checklist perjalanan riset</h2>
                    <p class="mt-1 text-sm text-slate-600">Ikuti langkah ini untuk menyelesaikan riset secara bertahap.</p>
                </div>
                @if ($journey['project']['target_finished_at'])
                    <p class="text-sm font-semibold text-slate-600">Target: {{ $journey['project']['target_finished_at'] }}</p>
                @endif
            </div>

            <div class="mt-6 grid gap-4">
                @foreach ($journey['steps'] as $step)
                    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="grid gap-4 lg:grid-cols-[1rem_1fr_auto] lg:items-start">
                            <div class="hidden h-full w-1 rounded-full {{ $barClass($step['status']) }} lg:block"></div>
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="text-base font-semibold">{{ $loop->iteration }}. {{ $step['label'] }}</h3>
                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass($step['status']) }}">
                                        {{ $step['status_label'] }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $step['description'] }}</p>
                                @if ($step['metrics'] !== [])
                                    <dl class="mt-4 flex flex-wrap gap-2">
                                        @foreach ($step['metrics'] as $label => $value)
                                            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                                                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $value }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @endif
                            </div>
                            <div class="lg:text-right">
                                <a href="{{ $step['action_url'] }}" class="inline-flex rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">
                                    {{ $step['action_label'] }}
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </main>
</body>
</html>
