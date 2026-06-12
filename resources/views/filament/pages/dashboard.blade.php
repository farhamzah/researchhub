<x-filament-panels::page>
    @php
        $stats = [
            ['label' => 'Research Projects', 'value' => $projectCount, 'description' => 'Scoped workspaces', 'accent' => '#2563eb'],
            ['label' => 'Documents', 'value' => $documentCount, 'description' => 'Research files', 'accent' => '#059669'],
            ['label' => 'Surveys', 'value' => $surveyCount, 'description' => 'Instruments', 'accent' => '#0891b2'],
            ['label' => 'Analysis Results', 'value' => $analysisResultCount, 'description' => 'Draft outputs', 'accent' => '#4f46e5'],
            ['label' => 'Pending Reviews', 'value' => $activeReviewCount, 'description' => 'Active review links', 'accent' => '#d97706'],
        ];

        $quickActions = [
            [
                'label' => 'Open Research Projects',
                'description' => 'Manage project workspaces and open project timelines.',
                'url' => route('filament.admin.resources.projects.research-projects.index'),
                'initial' => 'P',
            ],
            [
                'label' => 'Open Documents',
                'description' => 'Review documents, versions, metadata, and review links.',
                'url' => route('filament.admin.resources.documents.index'),
                'initial' => 'D',
            ],
            [
                'label' => 'Open Surveys',
                'description' => 'Manage instruments, responses, scoring, and analysis.',
                'url' => route('filament.admin.resources.surveys.index'),
                'initial' => 'S',
            ],
            [
                'label' => 'Google Drive Settings',
                'description' => 'Connect Drive before storing research files.',
                'url' => route('filament.admin.pages.settings.google-drive'),
                'initial' => 'G',
            ],
        ];
    @endphp

    <style>
        .rh-dashboard {
            display: grid;
            gap: 1.5rem;
        }

        .rh-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
        }

        .rh-hero {
            position: relative;
            overflow: hidden;
            padding: clamp(1.5rem, 3vw, 2.4rem);
            color: #ffffff;
            background:
                radial-gradient(circle at 82% 12%, rgba(45, 212, 191, 0.28), transparent 28%),
                linear-gradient(135deg, #0f172a 0%, #1d4ed8 52%, #0f766e 100%);
        }

        .rh-hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 1.25rem;
            grid-template-columns: minmax(0, 1fr);
        }

        .rh-badge {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.38rem 0.75rem;
            color: rgba(255, 255, 255, 0.92);
            font-size: 0.78rem;
            font-weight: 700;
        }

        .rh-hero-title {
            margin-top: 0.95rem;
            max-width: 46rem;
            font-size: clamp(2rem, 4vw, 3.1rem);
            line-height: 1.02;
            font-weight: 850;
        }

        .rh-hero-copy {
            margin-top: 0.85rem;
            max-width: 40rem;
            color: rgba(255, 255, 255, 0.82);
            font-size: 1rem;
            line-height: 1.7;
        }

        .rh-hero-actions {
            margin-top: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .rh-primary-action,
        .rh-secondary-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.65rem;
            border-radius: 12px;
            padding: 0.7rem 1rem;
            font-size: 0.9rem;
            font-weight: 800;
            text-decoration: none;
        }

        .rh-primary-action {
            background: #ffffff;
            color: #1e3a8a;
        }

        .rh-secondary-action {
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.28);
            background: rgba(255, 255, 255, 0.1);
        }

        .rh-drive-panel {
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            background: rgba(255, 255, 255, 0.12);
            padding: 1rem;
        }

        .rh-drive-label {
            color: rgba(255, 255, 255, 0.68);
            font-size: 0.78rem;
            font-weight: 800;
        }

        .rh-drive-value {
            margin-top: 0.3rem;
            font-size: 1.1rem;
            font-weight: 850;
        }

        .rh-drive-copy {
            margin-top: 0.45rem;
            color: rgba(255, 255, 255, 0.76);
            font-size: 0.86rem;
            line-height: 1.5;
        }

        .rh-stat-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
        }

        .rh-stat-card {
            position: relative;
            overflow: hidden;
            min-height: 8.75rem;
            padding: 1.15rem;
        }

        .rh-stat-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 0.3rem;
            background: var(--rh-accent, #2563eb);
        }

        .rh-stat-label {
            color: #475569;
            font-size: 0.78rem;
            font-weight: 850;
        }

        .rh-stat-value {
            margin-top: 0.65rem;
            color: #0f172a;
            font-size: 2.45rem;
            line-height: 1;
            font-weight: 900;
        }

        .rh-stat-description {
            margin-top: 0.75rem;
            color: #64748b;
            font-size: 0.84rem;
            line-height: 1.5;
        }

        .rh-main-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 25rem), 1fr));
        }

        .rh-section-card {
            padding: 1.25rem;
        }

        .rh-eyebrow {
            color: #2563eb;
            font-size: 0.76rem;
            font-weight: 850;
        }

        .rh-section-title {
            margin-top: 0.25rem;
            color: #0f172a;
            font-size: 1.15rem;
            font-weight: 850;
        }

        .rh-step-list {
            margin-top: 1rem;
            display: grid;
            gap: 0.78rem;
            padding: 0;
            list-style: none;
        }

        .rh-step {
            display: grid;
            grid-template-columns: 1.75rem minmax(0, 1fr);
            gap: 0.75rem;
            align-items: start;
        }

        .rh-step-number {
            display: inline-flex;
            height: 1.75rem;
            width: 1.75rem;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 0.78rem;
            font-weight: 900;
        }

        .rh-step-text {
            color: #334155;
            font-size: 0.92rem;
            line-height: 1.55;
        }

        .rh-timeline-note {
            margin-top: 1rem;
            border-top: 1px solid #e2e8f0;
            padding-top: 1rem;
            color: #64748b;
            font-size: 0.85rem;
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
            min-height: 8.5rem;
            gap: 0.85rem;
            border: 1px solid #dbe4ef;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            padding: 1rem;
            color: inherit;
            text-decoration: none;
            transition: transform 150ms ease, border-color 150ms ease, box-shadow 150ms ease;
        }

        .rh-action-tile:hover {
            transform: translateY(-2px);
            border-color: #60a5fa;
            box-shadow: 0 14px 28px rgba(37, 99, 235, 0.13);
        }

        .rh-action-head {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .rh-action-initial {
            display: inline-flex;
            height: 2.1rem;
            width: 2.1rem;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 900;
        }

        .rh-action-title {
            color: #0f172a;
            font-size: 0.95rem;
            font-weight: 850;
        }

        .rh-action-copy {
            color: #64748b;
            font-size: 0.82rem;
            line-height: 1.5;
        }

        .rh-action-link {
            color: #1d4ed8;
            font-size: 0.82rem;
            font-weight: 850;
        }

        .dark .rh-card {
            background: #111827;
            border-color: #334155;
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.28);
        }

        .dark .rh-stat-label,
        .dark .rh-stat-description,
        .dark .rh-step-text,
        .dark .rh-timeline-note,
        .dark .rh-action-copy {
            color: #cbd5e1;
        }

        .dark .rh-stat-value,
        .dark .rh-section-title,
        .dark .rh-action-title {
            color: #f8fafc;
        }

        .dark .rh-action-tile {
            background: linear-gradient(180deg, #111827, #0f172a);
            border-color: #334155;
        }

        .dark .rh-action-initial,
        .dark .rh-step-number {
            background: rgba(59, 130, 246, 0.18);
            color: #93c5fd;
        }

        @media (min-width: 64rem) {
            .rh-hero-grid {
                grid-template-columns: minmax(0, 1fr) 18rem;
                align-items: end;
            }
        }
    </style>

    <div class="rh-dashboard">
        <section class="rh-card rh-hero" data-dashboard-card="hero">
            <div class="rh-hero-grid">
                <div>
                    <span class="rh-badge">Academic Research Workspace</span>
                    <h1 class="rh-hero-title">Welcome to ResearchHub</h1>
                    <p class="rh-hero-copy">
                        Research workspace for projects, documents, surveys, analysis, and academic drafts.
                    </p>
                    <div class="rh-hero-actions">
                        <a href="{{ route('filament.admin.resources.projects.research-projects.index') }}" class="rh-primary-action">Open Projects</a>
                        <a href="{{ route('filament.admin.resources.documents.index') }}" class="rh-secondary-action">Open Documents</a>
                    </div>
                </div>

                <aside class="rh-drive-panel" aria-label="Google Drive status">
                    <p class="rh-drive-label">Drive Status</p>
                    <p class="rh-drive-value">{{ $driveConnected ? 'Connected' : 'Not connected' }}</p>
                    <p class="rh-drive-copy">
                        {{ $driveConnected ? 'Research file storage is ready.' : 'Connect Drive before uploading research files.' }}
                    </p>
                </aside>
            </div>
        </section>

        <section class="rh-stat-grid" aria-label="ResearchHub workspace statistics">
            @foreach ($stats as $stat)
                <article class="rh-card rh-stat-card" data-dashboard-card="stat" style="--rh-accent: {{ $stat['accent'] }};">
                    <p class="rh-stat-label">{{ $stat['label'] }}</p>
                    <p class="rh-stat-value">{{ $stat['value'] }}</p>
                    <p class="rh-stat-description">{{ $stat['description'] }}</p>
                </article>
            @endforeach
        </section>

        <div class="rh-main-grid">
            <section class="rh-card rh-section-card" data-dashboard-card="next-steps">
                <p class="rh-eyebrow">Recommended flow</p>
                <h2 class="rh-section-title">Recommended next steps</h2>

                <ol class="rh-step-list">
                    @foreach ([
                        'Create or open a research project.',
                        'Connect Google Drive.',
                        'Upload proposal/chapter documents.',
                        'Create survey instruments.',
                        'Run descriptive analysis.',
                    ] as $index => $step)
                        <li class="rh-step">
                            <span class="rh-step-number">{{ $index + 1 }}</span>
                            <span class="rh-step-text">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>

                <p class="rh-timeline-note">
                    Open a project to manage its timeline. Timeline planning stays scoped to the selected research project.
                </p>
            </section>

            <section class="rh-card rh-section-card" data-dashboard-card="quick-actions">
                <p class="rh-eyebrow">Workspace shortcuts</p>
                <h2 class="rh-section-title">Quick Actions</h2>

                <div class="rh-action-grid">
                    @foreach ($quickActions as $action)
                        <a href="{{ $action['url'] }}" class="rh-action-tile">
                            <span class="rh-action-head">
                                <span class="rh-action-initial">{{ $action['initial'] }}</span>
                                <span class="rh-action-title">{{ $action['label'] }}</span>
                            </span>
                            <span class="rh-action-copy">{{ $action['description'] }}</span>
                            <span class="rh-action-link">Open &rarr;</span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
