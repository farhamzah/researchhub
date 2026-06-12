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
                    <span class="rh-badge">Academic Research Command Center</span>
                    <h1 class="rh-hero-title">Welcome to MyRiset</h1>
                    <p class="rh-hero-copy">
                        Platform manajemen riset, validasi ahli, bimbingan, dan laporan akademik.
                    </p>
                    <div class="rh-hero-actions">
                        <a href="{{ route('filament.admin.resources.projects.research-projects.index') }}" class="rh-button">Open Projects</a>
                        <a href="{{ route('filament.admin.resources.research-links.index') }}" class="rh-button-ghost">Open Research Links</a>
                    </div>
                </div>

                <aside class="rh-drive-panel" aria-label="Google Drive status" data-dashboard-card="drive-status">
                    <p class="rh-drive-label">Drive Status</p>
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

        <div class="rh-grid">
            <section class="rh-card rh-section" data-dashboard-card="active-projects">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Projects</p>
                        <h2 class="rh-section-title">Active Projects</h2>
                    </div>
                    <a href="{{ route('filament.admin.resources.projects.research-projects.index') }}" class="rh-link">Open all</a>
                </div>

                @if ($activeProjects->isEmpty())
                    <p class="rh-empty">
                        No projects yet. Create your first research project to organize your documents, surveys, and timeline.
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
                                    {{ $project['completed_tasks'] }} of {{ $project['total_tasks'] }} timeline tasks completed.
                                </p>
                                <div class="rh-row-actions">
                                    <a href="{{ $project['project_url'] }}" class="rh-link">Open Projects</a>
                                    <a href="{{ $project['timeline_url'] }}" class="rh-link">Open Timeline</a>
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
                        <h2 class="rh-section-title">Timeline Focus</h2>
                    </div>
                </div>

                <div class="rh-focus-grid">
                    <div class="rh-focus-cell">
                        <p class="rh-focus-value">{{ $timelineSummary['delayed_tasks'] }}</p>
                        <p class="rh-focus-label">Delayed Tasks</p>
                    </div>
                    <div class="rh-focus-cell">
                        <p class="rh-focus-value">{{ $timelineSummary['upcoming_tasks'] }}</p>
                        <p class="rh-focus-label">Upcoming 14 Days</p>
                    </div>
                    <div class="rh-focus-cell">
                        <p class="rh-focus-value">{{ $timelineSummary['active_tasks'] }}</p>
                        <p class="rh-focus-label">Active Tasks</p>
                    </div>
                </div>

                @if ($timelineSummary['next_due_task'])
                    <article class="rh-item" style="margin-top: 1rem;">
                        <h3 class="rh-item-title">Next due: {{ $timelineSummary['next_due_task']['title'] }}</h3>
                        <p class="rh-item-meta">
                            {{ $timelineSummary['next_due_task']['project'] }} | Due {{ $timelineSummary['next_due_task']['planned_end_date'] }}
                        </p>
                        <a href="{{ $timelineSummary['next_due_task']['url'] }}" class="rh-link">Open Timeline</a>
                    </article>
                @else
                    <p class="rh-empty">
                        No upcoming timeline tasks. Add timeline tasks inside a project to track dissertation milestones and deadlines.
                    </p>
                @endif
            </section>

            <section class="rh-card rh-section" data-dashboard-card="recent-documents">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Documents</p>
                        <h2 class="rh-section-title">Recent Documents</h2>
                    </div>
                    <a href="{{ route('filament.admin.resources.documents.index') }}" class="rh-link">Open all</a>
                </div>

                @forelse ($recentDocuments as $document)
                    @if ($loop->first)
                        <div class="rh-list">
                    @endif
                    <article class="rh-item">
                        <div class="rh-item-top">
                            <div>
                                <h3 class="rh-item-title">{{ $document['title'] }}</h3>
                                <p class="rh-item-meta">{{ $document['project'] }} | Updated {{ $document['updated_at'] }}</p>
                            </div>
                            <span class="rh-pill">{{ $document['status'] }}</span>
                        </div>
                        <a href="{{ $document['url'] }}" class="rh-link">Open Documents</a>
                    </article>
                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="rh-empty">
                        No documents yet. Upload or create your first research document.
                    </p>
                @endforelse
            </section>

            <section class="rh-card rh-section" data-dashboard-card="recent-surveys">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Surveys</p>
                        <h2 class="rh-section-title">Recent Surveys</h2>
                    </div>
                    <a href="{{ route('filament.admin.resources.surveys.index') }}" class="rh-link">Open all</a>
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
                                    {{ $survey['project'] }} | {{ $survey['responses_count'] }} responses
                                </p>
                            </div>
                            <span class="rh-pill">{{ $survey['status'] }}</span>
                        </div>
                        <a href="{{ $survey['url'] }}" class="rh-link">Open Survey</a>
                    </article>
                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="rh-empty">
                        No surveys yet. Create your first survey instrument.
                    </p>
                @endforelse
            </section>

            <section class="rh-card rh-section" data-dashboard-card="pinned-research-links">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Resources</p>
                        <h2 class="rh-section-title">Pinned Research Links</h2>
                    </div>
                    <a href="{{ route('filament.admin.resources.research-links.index') }}" class="rh-link">Open library</a>
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
                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="rh-link">Open Link</a>
                    </article>
                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="rh-empty">
                        No pinned research links yet. Add journals, OJS pages, regulations, repositories, or datasets for quick access.
                    </p>
                @endforelse
            </section>

            <section class="rh-card rh-section" data-dashboard-card="recent-analysis">
                <div class="rh-section-head">
                    <div>
                        <p class="rh-section-kicker">Analysis</p>
                        <h2 class="rh-section-title">Recent Analysis Results</h2>
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
                        <p class="rh-item-meta">Updated {{ $result['updated_at'] }}</p>
                        <a href="{{ $result['url'] }}" class="rh-link">Open Analysis</a>
                    </article>
                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="rh-empty">
                        No analysis results yet. Run descriptive analysis from a survey when response data is ready.
                    </p>
                @endforelse
            </section>
        </div>

        <section class="rh-card rh-section" data-dashboard-card="quick-actions">
            <div class="rh-section-head">
                <div>
                    <p class="rh-section-kicker">Workspace shortcuts</p>
                    <h2 class="rh-section-title">Quick Actions</h2>
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
                        <span class="rh-link">Open</span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
