@php
    use App\Modules\Projects\Services\ProjectResearchJourneyService;

    $barClass = fn (string $status): string => match ($status) {
        ProjectResearchJourneyService::STATUS_COMPLETED => 'bg-emerald-600',
        ProjectResearchJourneyService::STATUS_IN_PROGRESS => 'bg-blue-600',
        ProjectResearchJourneyService::STATUS_NEEDS_ATTENTION => 'bg-amber-500',
        ProjectResearchJourneyService::STATUS_BLOCKED => 'bg-red-600',
        default => 'bg-slate-300',
    };

    $journeyUiText = static fn (string $text): string => strtr($text, [
        'Setup Project' => 'Siapkan Proyek',
        'Project' => 'Proyek',
        'project' => 'proyek',
        'Survey' => 'Survei',
        'survey' => 'survei',
        'Timeline' => 'Jadwal',
        'timeline' => 'jadwal',
        'milestone' => 'tonggak',
        'deadline' => 'tenggat',
        'Review' => 'Peninjauan',
        'review' => 'peninjauan',
        'Feedback' => 'Umpan Balik',
        'feedback' => 'umpan balik',
        'Mapping' => 'Pemetaan',
        'mapping' => 'pemetaan',
    ]);
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alur Riset - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl overflow-x-hidden px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        <div data-ui="myriset-page-header" class="mb-6 flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Alur Riset MyRiset</p>
                <h1 class="mt-2 text-3xl font-semibold">Alur Riset</h1>
                <p class="mt-2 break-words text-sm text-slate-600">{{ $project->title }} - {{ $journey['project']['status'] }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('filament.admin.resources.projects.research-projects.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                    Kembali ke Proyek
                </a>
                <a href="{{ url('/admin') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                    Beranda
                </a>
                <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                        Keluar
                    </button>
                </form>
            </div>
        </div>

        <section data-ui="myriset-section-card" class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-6 lg:grid-cols-[1fr_22rem] lg:items-end">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-semibold">Langkah berikutnya</h2>
                        <x-myriset.status-badge :status="$journey['next_step']['status']" :label="$journey['next_step']['status_label'] ?? 'Selesai'" size="xs" />
                    </div>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">{{ $journeyUiText($journey['next_step']['description']) }}</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <x-myriset.action-link :href="$journey['next_step']['action_url']" variant="primary">
                            {{ $journeyUiText($journey['next_step']['action_label']) }}
                        </x-myriset.action-link>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kelengkapan Alur Aplikasi</p>
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
                    <p class="mt-3 text-xs leading-5 text-slate-500">Persentase ini menunjukkan kelengkapan alur di aplikasi, bukan ukuran kelulusan disertasi, validitas ilmiah, atau izin pengumpulan data.</p>
                </div>
            </div>
        </section>

        <details class="mb-6 rounded-lg border border-slate-200 bg-white shadow-sm" data-journey-secondary-summary>
            <summary class="cursor-pointer rounded-lg px-5 py-4 text-sm font-semibold text-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                Lihat ringkasan tambahan
            </summary>
            <div class="grid gap-4 border-t border-slate-200 p-4 lg:grid-cols-3">
                <x-academic-output-block
                    title="Ringkasan Progres Proyek"
                    description="Narasi progres riset dari daftar alur, status, dan langkah berikutnya."
                    :narrative="$academicNarratives['projectProgress']"
                    source="Sumber: Alur Riset"
                />
                <x-academic-output-block
                    title="Ringkasan Progres Dokumen"
                    description="Ringkasan status draf, peninjauan, revisi, dan tindak lanjut dokumen."
                    :narrative="$academicNarratives['documentProgress']"
                    source="Sumber: Alur Revisi Dokumen"
                />
                <x-academic-output-block
                    title="Ringkasan Tindak Lanjut Revisi"
                    description="Ringkasan tindak lanjut revisi lintas sesi bimbingan."
                    :narrative="$academicNarratives['followUp']"
                    source="Sumber: Alur Riset"
                />
            </div>
        </details>

        <section data-ui="myriset-section-card" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Sebelas langkah Alur Riset</h2>
                    <p class="mt-1 text-sm text-slate-600">Buka setiap langkah untuk melihat keterangan, metrik, dan tindakan yang tersedia. Status dan rumus progres tetap menggunakan data existing.</p>
                </div>
                @if ($journey['project']['target_finished_at'])
                    <p class="text-sm font-semibold text-slate-600">Target: {{ $journey['project']['target_finished_at'] }}</p>
                @endif
            </div>

            <div class="mt-6 grid gap-4">
                @foreach ($journey['steps'] as $step)
                    <details class="group rounded-lg border border-slate-200 bg-white shadow-sm" data-journey-step data-step-key="{{ $step['key'] }}">
                        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 rounded-lg p-5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="h-8 w-1 shrink-0 rounded-full {{ $barClass($step['status']) }}"></span>
                                <span class="break-words text-base font-semibold">{{ $loop->iteration }}. {{ $journeyUiText($step['label']) }}</span>
                            </span>
                            <x-myriset.status-badge :status="$step['status']" :label="$step['status_label']" size="xs" />
                        </summary>
                        <div class="grid gap-4 border-t border-slate-200 p-5 lg:grid-cols-[1fr_auto] lg:items-start" data-journey-step-details>
                            <div class="min-w-0">
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $journeyUiText($step['description']) }}</p>
                                @if ($step['metrics'] !== [])
                                    <dl class="mt-4 flex flex-wrap gap-2">
                                        @foreach ($step['metrics'] as $label => $value)
                                            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $journeyUiText($label) }}</dt>
                                                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $value }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @endif
                            </div>
                            <div class="lg:text-right">
                                <x-myriset.action-link :href="$step['action_url']">
                                    {{ $journeyUiText($step['action_label']) }}
                                </x-myriset.action-link>
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    </main>
</body>
</html>
