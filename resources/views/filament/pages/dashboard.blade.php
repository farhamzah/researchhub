<x-filament-panels::page>
    @php
        $stats = [
            ['label' => 'Research Projects', 'value' => $projectCount, 'description' => 'Active workspaces you can access'],
            ['label' => 'Documents', 'value' => $documentCount, 'description' => 'Proposal, chapter, and research files'],
            ['label' => 'Surveys', 'value' => $surveyCount, 'description' => 'Research instruments and responses'],
            ['label' => 'Analysis Results', 'value' => $analysisResultCount, 'description' => 'Descriptive analysis outputs'],
            ['label' => 'Pending Reviews', 'value' => $activeReviewCount, 'description' => 'Active examiner or supervisor links'],
        ];

        $quickActions = [
            [
                'label' => 'Open Research Projects',
                'description' => 'Manage project workspaces and access project timelines.',
                'url' => route('filament.admin.resources.projects.research-projects.index'),
            ],
            [
                'label' => 'Open Documents',
                'description' => 'Review document metadata, versions, and review links.',
                'url' => route('filament.admin.resources.documents.index'),
            ],
            [
                'label' => 'Open Surveys',
                'description' => 'Manage instruments, responses, scoring, and analysis.',
                'url' => route('filament.admin.resources.surveys.index'),
            ],
            [
                'label' => 'Google Drive Settings',
                'description' => 'Connect Drive for safe research file storage.',
                'url' => route('filament.admin.pages.settings.google-drive'),
            ],
        ];
    @endphp

    <div class="researchhub-dashboard" style="display: grid; gap: 1.5rem;">
        <section
            class="researchhub-welcome"
            style="border: 1px solid rgba(59, 130, 246, 0.24); border-radius: 1.25rem; background: linear-gradient(135deg, #1d4ed8, #1e3a8a); color: white; padding: clamp(1.5rem, 3vw, 2.25rem); box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);"
        >
            <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1.25rem;">
                <div style="max-width: 48rem;">
                    <span style="display: inline-flex; align-items: center; border-radius: 999px; background: rgba(255, 255, 255, 0.14); padding: 0.35rem 0.75rem; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.02em;">
                        Academic Research Workspace
                    </span>
                    <h1 style="margin-top: 1rem; font-size: clamp(1.8rem, 3vw, 2.55rem); font-weight: 800; line-height: 1.08;">
                        Welcome to ResearchHub
                    </h1>
                    <p style="margin-top: 0.75rem; max-width: 42rem; color: rgba(255, 255, 255, 0.86); font-size: 1rem; line-height: 1.65;">
                        Manage your research projects, documents, surveys, analysis, and academic drafts in one place.
                    </p>
                </div>

                <div style="min-width: 14rem; border-radius: 1rem; background: rgba(255, 255, 255, 0.12); padding: 1rem; backdrop-filter: blur(8px);">
                    <p style="font-size: 0.78rem; font-weight: 700; color: rgba(255, 255, 255, 0.72);">Drive Status</p>
                    <p style="margin-top: 0.35rem; font-size: 1.05rem; font-weight: 800;">
                        {{ $driveConnected ? 'Connected' : 'Not connected' }}
                    </p>
                    <p style="margin-top: 0.4rem; color: rgba(255, 255, 255, 0.75); font-size: 0.85rem; line-height: 1.45;">
                        {{ $driveConnected ? 'Research file storage is ready.' : 'Connect Drive before uploading research files.' }}
                    </p>
                </div>
            </div>
        </section>

        <section class="researchhub-stat-grid" style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(11.5rem, 1fr));">
            @foreach ($stats as $stat)
                <article
                    class="researchhub-stat-card"
                    style="border: 1px solid rgba(148, 163, 184, 0.28); border-radius: 1rem; background: var(--gray-50); padding: 1.15rem; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);"
                >
                    <p style="font-size: 0.78rem; font-weight: 700; color: var(--gray-600);">{{ $stat['label'] }}</p>
                    <p style="margin-top: 0.55rem; color: var(--gray-950); font-size: 2rem; font-weight: 800; line-height: 1;">{{ $stat['value'] }}</p>
                    <p style="margin-top: 0.7rem; color: var(--gray-500); font-size: 0.82rem; line-height: 1.45;">{{ $stat['description'] }}</p>
                </article>
            @endforeach
        </section>

        <div class="researchhub-dashboard-grid" style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 24rem), 1fr));">
            <section
                class="researchhub-next-steps-card"
                style="border: 1px solid rgba(148, 163, 184, 0.28); border-radius: 1rem; background: var(--gray-50); padding: 1.25rem; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);"
            >
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                    <div>
                        <p style="font-size: 0.78rem; font-weight: 700; color: var(--primary-600);">Recommended flow</p>
                        <h2 style="margin-top: 0.25rem; color: var(--gray-950); font-size: 1.15rem; font-weight: 800;">Recommended next steps</h2>
                    </div>
                    <span style="border-radius: 999px; background: rgba(59, 130, 246, 0.12); color: var(--primary-700); padding: 0.35rem 0.65rem; font-size: 0.76rem; font-weight: 700;">Start here</span>
                </div>

                <ol style="margin-top: 1rem; display: grid; gap: 0.75rem; padding: 0; list-style: none;">
                    @foreach ([
                        'Create or open a research project.',
                        'Connect Google Drive.',
                        'Upload research documents.',
                        'Create survey instruments.',
                        'Run descriptive analysis.',
                    ] as $index => $step)
                        <li style="display: flex; gap: 0.75rem; align-items: flex-start;">
                            <span style="display: inline-flex; height: 1.65rem; width: 1.65rem; flex: 0 0 auto; align-items: center; justify-content: center; border-radius: 999px; background: rgba(59, 130, 246, 0.12); color: var(--primary-700); font-size: 0.8rem; font-weight: 800;">
                                {{ $index + 1 }}
                            </span>
                            <span style="color: var(--gray-700); font-size: 0.92rem; line-height: 1.55;">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>

                <p style="margin-top: 1rem; border-top: 1px solid rgba(148, 163, 184, 0.24); padding-top: 1rem; color: var(--gray-500); font-size: 0.85rem; line-height: 1.55;">
                    Timeline planning is available from each project row, keeping milestones scoped to the selected research project.
                </p>
            </section>

            <section
                class="researchhub-quick-actions-card"
                style="border: 1px solid rgba(148, 163, 184, 0.28); border-radius: 1rem; background: var(--gray-50); padding: 1.25rem; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);"
            >
                <div>
                    <p style="font-size: 0.78rem; font-weight: 700; color: var(--primary-600);">Workspace shortcuts</p>
                    <h2 style="margin-top: 0.25rem; color: var(--gray-950); font-size: 1.15rem; font-weight: 800;">Quick Actions</h2>
                </div>

                <div style="margin-top: 1rem; display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fit, minmax(12.5rem, 1fr));">
                    @foreach ($quickActions as $action)
                        <a
                            href="{{ $action['url'] }}"
                            class="researchhub-action-tile"
                            style="display: flex; min-height: 7.5rem; flex-direction: column; justify-content: space-between; border: 1px solid rgba(148, 163, 184, 0.28); border-radius: 0.9rem; background: var(--gray-100); padding: 1rem; text-decoration: none; transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;"
                        >
                            <span>
                                <span style="display: block; color: var(--gray-950); font-size: 0.96rem; font-weight: 800;">{{ $action['label'] }}</span>
                                <span style="margin-top: 0.45rem; display: block; color: var(--gray-500); font-size: 0.82rem; line-height: 1.45;">{{ $action['description'] }}</span>
                            </span>
                            <span style="margin-top: 1rem; color: var(--primary-700); font-size: 0.82rem; font-weight: 800;">Open &rarr;</span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
