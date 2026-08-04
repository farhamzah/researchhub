<x-filament-panels::page>
    <style>
        .fi-body,
        .fi-main,
        .fi-page-content {
            background: #f8fafc;
        }

        html.dark .fi-body,
        html.dark .fi-main,
        html.dark .fi-page-content {
            background: #f8fafc;
        }

        .rh-dashboard {
            display: grid;
            gap: 1.25rem;
            color: #0f172a;
        }

        .rh-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.07);
        }

        .rh-hero {
            overflow: hidden;
            padding: clamp(1.25rem, 3vw, 2.25rem);
            color: #0f172a;
            background:
                linear-gradient(135deg, rgba(37, 99, 235, 0.12) 0%, rgba(15, 118, 110, 0.1) 52%, rgba(255, 255, 255, 0.96) 100%),
                #ffffff;
        }

        .rh-hero-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 1fr);
        }

        .rh-badge {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            border-radius: 999px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            padding: 0.35rem 0.72rem;
            color: #1d4ed8;
            font-size: 0.78rem;
            font-weight: 800;
        }

        .rh-hero-title {
            margin-top: 0.9rem;
            max-width: 46rem;
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1.02;
            font-weight: 850;
        }

        .rh-hero-copy {
            margin-top: 0.85rem;
            max-width: 42rem;
            color: #475569;
            font-size: 1rem;
            line-height: 1.65;
        }

        .rh-hero-actions,
        .rh-row-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
        }

        .rh-hero-actions {
            margin-top: 1.2rem;
        }

        .rh-button,
        .rh-button-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.45rem;
            border-radius: 10px;
            padding: 0.62rem 0.9rem;
            font-size: 0.86rem;
            font-weight: 800;
            text-decoration: none;
        }

        .rh-button {
            background: #2563eb;
            color: #ffffff;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.18);
        }

        .rh-button-ghost {
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            background: #ffffff;
        }

        .rh-drive-panel {
            border-radius: 12px;
            border: 1px solid #dbeafe;
            background: rgba(255, 255, 255, 0.82);
            padding: 1rem;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }

        .rh-drive-label,
        .rh-section-kicker,
        .rh-item-meta,
        .rh-empty {
            font-size: 0.78rem;
        }

        .rh-drive-label {
            color: #2563eb;
            font-weight: 850;
        }

        .rh-drive-value {
            margin-top: 0.25rem;
            font-size: 1.08rem;
            font-weight: 850;
        }

        .rh-drive-copy {
            margin-top: 0.45rem;
            color: #64748b;
            font-size: 0.85rem;
            line-height: 1.45;
        }

        .rh-stat-grid {
            display: grid;
            gap: 0.9rem;
            grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
        }

        .rh-stat-card {
            position: relative;
            overflow: hidden;
            min-height: 7.5rem;
            padding: 1rem;
        }

        .rh-stat-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 0.28rem;
            background: var(--rh-accent, #2563eb);
        }

        .rh-stat-label {
            color: #475569;
            font-size: 0.78rem;
            font-weight: 850;
        }

        .rh-stat-value {
            margin-top: 0.55rem;
            color: #0f172a;
            font-size: 2.1rem;
            line-height: 1;
            font-weight: 900;
        }

        .rh-stat-description,
        .rh-item-copy {
            color: #64748b;
            font-size: 0.82rem;
            line-height: 1.5;
        }

        .rh-stat-description {
            margin-top: 0.65rem;
        }

        .rh-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 25rem), 1fr));
        }

        .rh-section {
            padding: 1.15rem;
        }

        .rh-section-head {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            justify-content: space-between;
        }

        .rh-section-kicker {
            color: #2563eb;
            font-weight: 850;
        }

        .rh-section-title {
            margin-top: 0.2rem;
            color: #0f172a;
            font-size: 1.05rem;
            font-weight: 850;
        }

        .rh-list {
            margin-top: 1rem;
            display: grid;
            gap: 0.75rem;
        }

        .rh-item {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.9rem;
            background: #f8fafc;
        }

        .rh-item-top {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            justify-content: space-between;
        }

        .rh-item-title {
            color: #0f172a;
            font-size: 0.92rem;
            font-weight: 850;
        }

        .rh-item-meta {
            margin-top: 0.25rem;
            color: #64748b;
            line-height: 1.45;
        }

        .rh-pill {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            border-radius: 999px;
            background: #e0f2fe;
            color: #075985;
            padding: 0.25rem 0.55rem;
            font-size: 0.72rem;
            font-weight: 850;
            white-space: nowrap;
        }

        .rh-pill-risk {
            background: #fee2e2;
            color: #991b1b;
        }

        .rh-pill-warn {
            background: #fef3c7;
            color: #92400e;
        }

        .rh-item-risk {
            border-color: #fecaca;
            background: #fff7f7;
        }

        .rh-link {
            color: #1d4ed8;
            font-size: 0.8rem;
            font-weight: 850;
            text-decoration: none;
        }

        .rh-link:hover {
            text-decoration: underline;
        }

        .rh-progress {
            margin-top: 0.85rem;
            height: 0.55rem;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
        }

        .rh-progress > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #2563eb, #0f766e);
        }

        .rh-focus-grid {
            margin-top: 1rem;
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .rh-focus-cell {
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 0.85rem;
        }

        .rh-focus-value {
            color: #0f172a;
            font-size: 1.45rem;
            line-height: 1;
            font-weight: 900;
        }

        .rh-focus-label {
            margin-top: 0.35rem;
            color: #64748b;
            font-size: 0.74rem;
            font-weight: 800;
        }

        .rh-empty {
            margin-top: 1rem;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            background: #f8fafc;
            padding: 1rem;
            color: #64748b;
            line-height: 1.55;
        }

        .rh-action-grid {
            margin-top: 1rem;
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
        }

        .rh-action-tile {
            display: grid;
            min-height: 7.75rem;
            gap: 0.75rem;
            border: 1px solid #dbe4ef;
            border-radius: 12px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            padding: 1rem;
            color: inherit;
            text-decoration: none;
        }

        .rh-action-head {
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }

        .rh-action-initial {
            display: inline-flex;
            height: 2rem;
            width: 2rem;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 900;
        }

        .rh-action-title {
            color: #0f172a;
            font-size: 0.9rem;
            font-weight: 850;
        }

        .rh-action-center {
            padding: 1.15rem;
        }

        .rh-action-center-grid {
            margin-top: 1rem;
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr));
        }

        .rh-journey-grid {
            margin-top: 1rem;
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr));
        }

        .rh-journey-tile {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
            padding: 1rem;
        }

        .rh-journey-title-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .rh-journey-progress {
            margin-top: 0.85rem;
            height: 0.5rem;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
        }

        .rh-journey-progress > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #2563eb;
        }

        .rh-onboarding-list {
            margin-top: 1rem;
            display: grid;
            gap: 0.65rem;
        }

        .rh-onboarding-item {
            display: grid;
            gap: 0.2rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            padding: 0.85rem;
        }

        @media (min-width: 64rem) {
            .rh-hero-grid {
                grid-template-columns: minmax(0, 1fr) 19rem;
                align-items: end;
            }
        }

        @media (max-width: 32rem) {
            .rh-focus-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="rh-dashboard">
        <section class="rh-card rh-hero" data-dashboard-card="hero">
            <div class="rh-hero-grid">
                <div>
                    <span class="rh-badge">Pusat Kendali Riset Akademik</span>
                    <h1 class="rh-hero-title">Selamat datang di MyRiset</h1>
                    <p class="rh-hero-copy">
                        Platform manajemen riset, validasi ahli, bimbingan, dan laporan akademik.
                    </p>
                    <div class="rh-hero-actions">
                        <a href="{{ route('filament.admin.resources.projects.research-projects.index') }}" class="rh-button">Lihat Project</a>
                        <a href="{{ route('filament.admin.resources.research-links.index') }}" class="rh-button-ghost">Buka Link Riset</a>
                    </div>
                </div>

                <aside class="rh-drive-panel" aria-label="Status Google Drive" data-dashboard-card="drive-status">
                    <p class="rh-drive-label">Status Google Drive</p>
                    <p class="rh-drive-value">{{ $driveStatus['label'] }}</p>
                    <p class="rh-drive-copy">{{ $driveStatus['description'] }}</p>
                </aside>
            </div>
        </section>

        <section class="rh-stat-grid" aria-label="MyRiset workspace statistics">
            @foreach ($stats as $stat)
                <article class="rh-card rh-stat-card" data-dashboard-card="stat" style="--rh-accent: {{ $stat['accent'] }};">
                    <p class="rh-stat-label">{{ $stat['label'] }}</p>
                    <p class="rh-stat-value">{{ $stat['value'] }}</p>
                    <p class="rh-stat-description">{{ $stat['description'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="rh-card rh-action-center" data-dashboard-card="action-center">
            <div class="rh-section-head">
                <div>
                    <p class="rh-section-kicker">Pusat Tindakan</p>
                    <h2 class="rh-section-title">Yang Perlu Dikerjakan Sekarang</h2>
                </div>
                <a href="{{ route('filament.admin.resources.projects.research-projects.index') }}" class="rh-link">Lihat Project</a>
            </div>

            @if ($actionCenterItems->isEmpty())
                <p class="rh-empty">
                    Belum ada tindak lanjut, validasi, feedback bimbingan, atau risiko timeline yang perlu ditangani.
                </p>
            @else
                <div class="rh-action-center-grid">
                    @foreach ($actionCenterItems as $item)
                        <article class="rh-item {{ $item['is_risk'] ? 'rh-item-risk' : '' }}">
                            <div class="rh-item-top">
                                <div>
                                    <h3 class="rh-item-title">{{ $item['title'] }}</h3>
                                    <p class="rh-item-meta">{{ $item['context'] }}</p>
                                    @if ($item['date_label'])
                                        <p class="rh-item-meta">{{ $item['date_label'] }}</p>
                                    @endif
                                </div>
                                <span class="rh-pill {{ $item['is_risk'] ? 'rh-pill-risk' : '' }}">{{ $item['badge'] }}</span>
                            </div>
                            <a href="{{ $item['url'] }}" class="rh-link">{{ $item['action_label'] }}</a>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rh-card rh-section" data-dashboard-card="research-journey">
            <div class="rh-section-head">
                <div>
                    <p class="rh-section-kicker">Alur Riset</p>
                    <h2 class="rh-section-title">Lanjutkan Alur Riset</h2>
                </div>
                <a href="{{ route('filament.admin.resources.projects.research-projects.index') }}" class="rh-link">Lihat Project</a>
            </div>

            @if (! empty($onboardingChecklist))
                <p class="rh-item-copy" style="margin-top: 0.75rem;">
                    Mulai dari langkah kecil yang paling penting, lalu biarkan workspace riset terbentuk bertahap.
                </p>
                <div class="rh-onboarding-list">
                    @foreach ($onboardingChecklist as $item)
                        <a href="{{ $item['url'] }}" class="rh-onboarding-item">
                            <span class="rh-item-title">{{ $loop->iteration }}. {{ $item['label'] }}</span>
                            <span class="rh-item-copy">{{ $item['description'] }}</span>
                        </a>
                    @endforeach
                </div>
            @elseif ($journeyProjects->isEmpty())
                <p class="rh-empty">
                    Belum ada project riset. Buat project pertama untuk mulai mengelola dokumen, survey, timeline, dan bimbingan.
                </p>
            @else
                <div class="rh-journey-grid">
                    @foreach ($journeyProjects as $journeyProject)
                        <article class="rh-journey-tile">
                            <div class="rh-journey-title-row">
                                <div>
                                    <h3 class="rh-item-title">{{ $journeyProject['title'] }}</h3>
                                    <p class="rh-item-meta">{{ $journeyProject['status'] }}</p>
                                </div>
                                <span class="rh-pill">{{ $journeyProject['progress_percentage'] }}%</span>
                            </div>
                            <div class="rh-journey-progress" aria-label="Research journey progress">
                                <span style="width: {{ $journeyProject['progress_percentage'] }}%;"></span>
                            </div>
                            <p class="rh-item-copy" style="margin-top: 0.8rem;">
                                {{ $journeyProject['next_step']['description'] }}
                            </p>
                            <a href="{{ $journeyProject['url'] }}" class="rh-link">Buka Alur Riset</a>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="rh-grid">
            <section class="rh-card rh-section" data-dashboard-card="pending-follow-ups">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Tindak Lanjut</p>
                        <h2 class="rh-section-title">Tindak Lanjut Revisi</h2>
                    </div>
                </div>

                @forelse ($pendingFollowUps as $item)
                    @if ($loop->first)
                        <div class="rh-list">
                    @endif
                    <article class="rh-item {{ $item['is_overdue'] ? 'rh-item-risk' : '' }}">
                        <div class="rh-item-top">
                            <div>
                                <h3 class="rh-item-title">{{ $item['title'] }}</h3>
                                <p class="rh-item-meta">{{ $item['project'] }} | {{ $item['session'] }}</p>
                                <p class="rh-item-meta">
                                    @if ($item['due_date'])
                                        Batas waktu {{ $item['due_date'] }}
                                    @else
                                        Tanpa batas waktu
                                    @endif
                                </p>
                            </div>
                            <span class="rh-pill {{ $item['is_overdue'] ? 'rh-pill-risk' : 'rh-pill-warn' }}">{{ $item['priority'] }}</span>
                        </div>
                        <p class="rh-item-meta">Status: {{ $item['status'] }}</p>
                        <a href="{{ $item['url'] }}" class="rh-link">Buka Bimbingan</a>
                    </article>
                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="rh-empty">
                        Tidak ada follow-up revisi yang sedang berjalan.
                    </p>
                @endforelse
            </section>

            <section class="rh-card rh-section" data-dashboard-card="validation-pending">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Validasi Ahli</p>
                        <h2 class="rh-section-title">Validasi Ahli Menunggu</h2>
                    </div>
                    <a href="{{ route('filament.admin.resources.surveys.index') }}" class="rh-link">Buka Survey</a>
                </div>

                @forelse ($validationPending as $item)
                    @if ($loop->first)
                        <div class="rh-list">
                    @endif
                    <article class="rh-item">
                        <div class="rh-item-top">
                            <div>
                                <h3 class="rh-item-title">{{ $item['survey'] }}</h3>
                                <p class="rh-item-meta">{{ $item['project'] }} | {{ $item['round'] }}</p>
                            </div>
                            <span class="rh-pill">{{ $item['round_status'] }}</span>
                        </div>
                        <p class="rh-item-meta">{{ $item['progress_label'] }}</p>
                        <a href="{{ $item['url'] }}" class="rh-link">Buka Validasi</a>
                    </article>
                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="rh-empty">
                        Tidak ada validasi ahli yang sedang menunggu submit.
                    </p>
                @endforelse
            </section>

            <section class="rh-card rh-section" data-dashboard-card="supervision-feedback">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Bimbingan</p>
                        <h2 class="rh-section-title">Feedback Bimbingan</h2>
                    </div>
                </div>

                @forelse ($recentSupervisionFeedback as $item)
                    @if ($loop->first)
                        <div class="rh-list">
                    @endif
                    <article class="rh-item {{ in_array($item['status_key'], [\App\Models\SupervisionSession::STATUS_REVISION_NEEDED, \App\Models\SupervisionSession::STATUS_FEEDBACK_SUBMITTED], true) ? 'rh-item-risk' : '' }}">
                        <div class="rh-item-top">
                            <div>
                                <h3 class="rh-item-title">{{ $item['title'] }}</h3>
                                <p class="rh-item-meta">{{ $item['project'] }}</p>
                                @if ($item['submitted_at'])
                                    <p class="rh-item-meta">Dikirim {{ $item['submitted_at'] }}</p>
                                @endif
                            </div>
                            <span class="rh-pill">{{ $item['decision'] }}</span>
                        </div>
                        <p class="rh-item-meta">Status: {{ $item['status'] }}</p>
                        <a href="{{ $item['url'] }}" class="rh-link">Buka Bimbingan</a>
                    </article>
                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="rh-empty">
                        Belum ada feedback bimbingan yang perlu ditindaklanjuti.
                    </p>
                @endforelse
            </section>

            <section class="rh-card rh-section" data-dashboard-card="timeline-risks">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Timeline Terlambat</p>
                        <h2 class="rh-section-title">Risiko Timeline</h2>
                    </div>
                </div>

                @forelse ($timelineRisks as $item)
                    @if ($loop->first)
                        <div class="rh-list">
                    @endif
                    <article class="rh-item rh-item-risk">
                        <div class="rh-item-top">
                            <div>
                                <h3 class="rh-item-title">{{ $item['title'] }}</h3>
                                <p class="rh-item-meta">
                                    {{ $item['project'] }} @if ($item['milestone']) | {{ $item['milestone'] }} @endif
                                </p>
                                <p class="rh-item-meta">Batas waktu {{ $item['planned_end_date'] }}</p>
                            </div>
                            <span class="rh-pill rh-pill-risk">{{ $item['status'] }}</span>
                        </div>
                        <a href="{{ $item['url'] }}" class="rh-link">Buka Timeline</a>
                    </article>
                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="rh-empty">
                        Tidak ada timeline task yang terlambat.
                    </p>
                @endforelse
            </section>

            <section class="rh-card rh-section" data-dashboard-card="active-projects">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Project</p>
                        <h2 class="rh-section-title">Project Aktif</h2>
                    </div>
                    <a href="{{ route('filament.admin.resources.projects.research-projects.index') }}" class="rh-link">Lihat semua</a>
                </div>

                @if ($activeProjects->isEmpty())
                    <p class="rh-empty">
                        Belum ada project. Buat project riset pertama untuk menyatukan dokumen, survey, timeline, dan bimbingan.
                    </p>
                @else
                    <div class="rh-list">
                        @foreach ($activeProjects as $project)
                            <article class="rh-item">
                                <div class="rh-item-top">
                                    <div>
                                        <h3 class="rh-item-title">{{ $project['title'] }}</h3>
                                        <p class="rh-item-meta">
                                            {{ $project['status'] }} @if ($project['target_finished_at']) | Target {{ $project['target_finished_at'] }} @endif
                                        </p>
                                    </div>
                                    <span class="rh-pill">{{ $project['progress_percentage'] }}%</span>
                                </div>
                                <div class="rh-progress" aria-label="Project timeline progress">
                                    <span style="width: {{ $project['progress_percentage'] }}%;"></span>
                                </div>
                                <p class="rh-item-meta">
                                    {{ $project['completed_tasks'] }} dari {{ $project['total_tasks'] }} tugas timeline selesai.
                                </p>
                                <div class="rh-row-actions">
                                    <a href="{{ $project['project_url'] }}" class="rh-link">Buka Project</a>
                                    <a href="{{ $project['timeline_url'] }}" class="rh-link">Buka Timeline</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rh-card rh-section" data-dashboard-card="timeline-focus">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Timeline</p>
                        <h2 class="rh-section-title">Fokus Timeline</h2>
                    </div>
                </div>

                <div class="rh-focus-grid">
                    <div class="rh-focus-cell">
                        <p class="rh-focus-value">{{ $timelineSummary['delayed_tasks'] }}</p>
                        <p class="rh-focus-label">Tugas Terlambat</p>
                    </div>
                    <div class="rh-focus-cell">
                        <p class="rh-focus-value">{{ $timelineSummary['upcoming_tasks'] }}</p>
                        <p class="rh-focus-label">Jatuh Tempo 14 Hari</p>
                    </div>
                    <div class="rh-focus-cell">
                        <p class="rh-focus-value">{{ $timelineSummary['active_tasks'] }}</p>
                        <p class="rh-focus-label">Tugas Aktif</p>
                    </div>
                </div>

                @if ($timelineSummary['next_due_task'])
                    <article class="rh-item" style="margin-top: 1rem;">
                        <h3 class="rh-item-title">Tenggat berikutnya: {{ $timelineSummary['next_due_task']['title'] }}</h3>
                        <p class="rh-item-meta">
                            {{ $timelineSummary['next_due_task']['project'] }} | Batas waktu {{ $timelineSummary['next_due_task']['planned_end_date'] }}
                        </p>
                        <a href="{{ $timelineSummary['next_due_task']['url'] }}" class="rh-link">Buka Timeline</a>
                    </article>
                @else
                    <p class="rh-empty">
                        Belum ada tugas timeline terdekat. Tambahkan tugas di dalam project agar milestone dan tenggat disertasi mudah dipantau.
                    </p>
                @endif
            </section>

            <section class="rh-card rh-section" data-dashboard-card="recent-documents">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Dokumen</p>
                        <h2 class="rh-section-title">Dokumen Terbaru</h2>
                    </div>
                    <a href="{{ route('filament.admin.resources.documents.index') }}" class="rh-link">Lihat semua</a>
                </div>

                @forelse ($recentDocuments as $document)
                    @if ($loop->first)
                        <div class="rh-list">
                    @endif
                    <article class="rh-item">
                        <div class="rh-item-top">
                            <div>
                                <h3 class="rh-item-title">{{ $document['title'] }}</h3>
                                <p class="rh-item-meta">{{ $document['project'] }} | Diperbarui {{ $document['updated_at'] }}</p>
                            </div>
                            <span class="rh-pill">{{ $document['status'] }}</span>
                        </div>
                        <a href="{{ $document['url'] }}" class="rh-link">Buka Dokumen</a>
                    </article>
                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="rh-empty">
                        Belum ada dokumen. Tambahkan proposal, bab, instrumen, atau draft artikel agar riwayat riset tersimpan rapi.
                    </p>
                @endforelse
            </section>

            <section class="rh-card rh-section" data-dashboard-card="recent-surveys">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Survey</p>
                        <h2 class="rh-section-title">Survey Terbaru</h2>
                    </div>
                    <a href="{{ route('filament.admin.resources.surveys.index') }}" class="rh-link">Lihat semua</a>
                </div>

                @forelse ($recentSurveys as $survey)
                    @if ($loop->first)
                        <div class="rh-list">
                    @endif
                    <article class="rh-item">
                        <div class="rh-item-top">
                            <div>
                                <h3 class="rh-item-title">{{ $survey['title'] }}</h3>
                                <p class="rh-item-meta">
                                    {{ $survey['project'] }} | {{ $survey['responses_count'] }} respons
                                </p>
                            </div>
                            <span class="rh-pill">{{ $survey['status'] }}</span>
                        </div>
                        <a href="{{ $survey['url'] }}" class="rh-link">Buka Survey</a>
                    </article>
                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="rh-empty">
                        Belum ada survey. Buat instrumen pertama saat pertanyaan, indikator, dan skoring mulai disiapkan.
                    </p>
                @endforelse
            </section>

            <section class="rh-card rh-section" data-dashboard-card="pinned-research-links">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Referensi</p>
                        <h2 class="rh-section-title">Link Riset Tersemat</h2>
                    </div>
                    <a href="{{ route('filament.admin.resources.research-links.index') }}" class="rh-link">Buka pustaka</a>
                </div>

                @forelse ($pinnedResearchLinks as $link)
                    @if ($loop->first)
                        <div class="rh-list">
                    @endif
                    <article class="rh-item">
                        <div class="rh-item-top">
                            <div>
                                <h3 class="rh-item-title">{{ $link['title'] }}</h3>
                                <p class="rh-item-meta">{{ $link['domain'] }}</p>
                            </div>
                            <span class="rh-pill">{{ $link['category'] }}</span>
                        </div>
                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="rh-link">Buka Link</a>
                    </article>
                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="rh-empty">
                        Belum ada link riset tersemat. Tambahkan jurnal, OJS, regulasi, repositori, atau dataset yang sering dipakai.
                    </p>
                @endforelse
            </section>

            <section class="rh-card rh-section" data-dashboard-card="recent-analysis">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Analisis</p>
                        <h2 class="rh-section-title">Analisis Terbaru</h2>
                    </div>
                </div>

                @forelse ($recentAnalysisResults as $result)
                    @if ($loop->first)
                        <div class="rh-list">
                    @endif
                    <article class="rh-item">
                        <h3 class="rh-item-title">{{ $result['title'] }}</h3>
                        <p class="rh-item-meta">
                            {{ $result['project'] }} @if ($result['survey']) | {{ $result['survey'] }} @endif
                        </p>
                        <p class="rh-item-meta">Diperbarui {{ $result['updated_at'] }}</p>
                        <a href="{{ $result['url'] }}" class="rh-link">Buka Analisis</a>
                    </article>
                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="rh-empty">
                        Belum ada hasil analisis. Jalankan analisis deskriptif dari survey setelah data respons siap.
                    </p>
                @endforelse
            </section>
        </div>

        <section class="rh-card rh-section" data-dashboard-card="quick-actions">
            <div class="rh-section-head">
                <div>
                    <p class="rh-section-kicker">Pintasan Workspace</p>
                    <h2 class="rh-section-title">Aksi Cepat</h2>
                </div>
            </div>

            <div class="rh-action-grid">
                @foreach ($quickActions as $action)
                    <a href="{{ $action['url'] }}" class="rh-action-tile">
                        <span class="rh-action-head">
                            <span class="rh-action-initial">{{ $action['initial'] }}</span>
                            <span class="rh-action-title">{{ $action['label'] }}</span>
                        </span>
                        <span class="rh-item-copy">{{ $action['description'] }}</span>
                        <span class="rh-link">Buka</span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
