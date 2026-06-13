<?php

namespace App\Modules\Dashboard\Services;

use App\Models\AnalysisResult;
use App\Models\Document;
use App\Models\DriveConnection;
use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Models\ResearchLink;
use App\Models\ResearchProject;
use App\Models\ReviewLink;
use App\Models\SupervisionFeedback;
use App\Models\SupervisionFollowUpItem;
use App\Models\SupervisionSession;
use App\Models\Survey;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\User;
use App\Modules\Projects\Services\ProjectResearchJourneyService;
use App\Modules\Projects\Services\ProjectTimelineProgressService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DashboardDataService
{
    public function __construct(
        private readonly ProjectTimelineProgressService $timelineProgress,
        private readonly ProjectResearchJourneyService $researchJourney,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $visibleProjectIds = $this->visibleProjectIds($user);
        $driveConnected = $this->driveConnected($user);

        return [
            'stats' => $this->stats($user, $visibleProjectIds),
            'driveStatus' => [
                'connected' => $driveConnected,
                'label' => $driveConnected ? 'Connected' : 'Not connected',
                'description' => $driveConnected
                    ? 'Google Drive is ready for MyRiset document storage.'
                    : 'Connect Google Drive before storing research files.',
            ],
            'activeProjects' => $this->activeProjects($user),
            'journeyProjects' => $this->journeyProjects($user),
            'onboardingChecklist' => $this->onboardingChecklist($visibleProjectIds),
            'timelineSummary' => $this->timelineSummary($visibleProjectIds),
            'recentDocuments' => $this->recentDocuments($user),
            'recentSurveys' => $this->recentSurveys($user),
            'recentAnalysisResults' => $this->recentAnalysisResults($user),
            'pinnedResearchLinks' => $this->pinnedResearchLinks($user),
            'actionCenterItems' => $this->actionCenterItems($visibleProjectIds),
            'pendingFollowUps' => $this->pendingFollowUps($visibleProjectIds),
            'validationPending' => $this->validationPending($visibleProjectIds),
            'recentSupervisionFeedback' => $this->recentSupervisionFeedback($visibleProjectIds),
            'timelineRisks' => $this->timelineRisks($visibleProjectIds),
            'quickActions' => $this->quickActions(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function journeyProjects(User $user): Collection
    {
        return ResearchProject::query()
            ->visibleTo($user)
            ->with([
                'documents.category',
                'surveys.questions',
                'surveys.indicators',
                'surveys.questionScorings',
                'surveys.responses',
                'surveys.analysisResults',
                'surveys.validationRounds.assignments.scores',
                'analysisResults',
                'milestones',
                'timelineTasks',
                'supervisionSessions.followUpItems',
            ])
            ->orderByRaw("case when status = 'active' then 0 when status = 'draft' then 1 else 2 end")
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(fn (ResearchProject $project): array => $this->researchJourney->summary($project));
    }

    /**
     * @param  Collection<int, string>  $visibleProjectIds
     * @return array<int, array<string, string>>
     */
    private function onboardingChecklist(Collection $visibleProjectIds): array
    {
        if ($visibleProjectIds->isNotEmpty()) {
            return [];
        }

        return [
            [
                'label' => 'Buat project riset pertama',
                'description' => 'Mulai dari workspace utama untuk disertasi atau penelitian.',
                'url' => route('filament.admin.resources.projects.research-projects.index'),
            ],
            [
                'label' => 'Tambahkan dokumen riset',
                'description' => 'Simpan proposal, bab, instrumen, atau draft artikel sebagai metadata aman.',
                'url' => route('filament.admin.resources.documents.index'),
            ],
            [
                'label' => 'Bangun instrumen survey',
                'description' => 'Siapkan pertanyaan, skoring, dan indikator saat sudah siap mengumpulkan data.',
                'url' => route('filament.admin.resources.surveys.index'),
            ],
            [
                'label' => 'Tambahkan validator ahli',
                'description' => 'Kelola calon validator sebelum membagikan link validasi.',
                'url' => route('filament.admin.resources.expert-validators.index'),
            ],
            [
                'label' => 'Mulai log bimbingan',
                'description' => 'Catat feedback pembimbing dan tindak lanjut revisi sejak awal.',
                'url' => route('filament.admin.resources.projects.research-projects.index'),
            ],
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function visibleProjectIds(User $user): Collection
    {
        return ResearchProject::query()
            ->visibleTo($user)
            ->pluck('id');
    }

    /**
     * @param  Collection<int, string>  $visibleProjectIds
     * @return array<int, array<string, mixed>>
     */
    private function stats(User $user, Collection $visibleProjectIds): array
    {
        $projectIds = $visibleProjectIds->all();

        return [
            [
                'label' => 'Research Projects',
                'value' => $visibleProjectIds->count(),
                'description' => 'Visible project workspaces',
                'accent' => '#2563eb',
            ],
            [
                'label' => 'Documents',
                'value' => Document::query()->visibleTo($user)->count(),
                'description' => 'Visible research files',
                'accent' => '#059669',
            ],
            [
                'label' => 'Surveys',
                'value' => Survey::query()->visibleTo($user)->count(),
                'description' => 'Visible instruments',
                'accent' => '#0891b2',
            ],
            [
                'label' => 'Analysis Results',
                'value' => $this->analysisResultQuery($user)->count(),
                'description' => 'Academic draft outputs',
                'accent' => '#7c3aed',
            ],
            [
                'label' => 'Research Links',
                'value' => ResearchLink::query()->visibleTo($user)->where('is_active', true)->count(),
                'description' => 'Active saved resources',
                'accent' => '#0f766e',
            ],
            [
                'label' => 'Timeline Tasks',
                'value' => $projectIds === []
                    ? 0
                    : ProjectTimelineTask::query()->whereIn('research_project_id', $projectIds)->count(),
                'description' => 'Scoped timeline items',
                'accent' => '#d97706',
            ],
            [
                'label' => 'Pending Reviews',
                'value' => $projectIds === []
                    ? 0
                    : ReviewLink::query()
                        ->whereIn('project_id', $projectIds)
                        ->where('status', ReviewLink::STATUS_ACTIVE)
                        ->count(),
                'description' => 'Active review links',
                'accent' => '#dc2626',
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function activeProjects(User $user): Collection
    {
        return ResearchProject::query()
            ->visibleTo($user)
            ->with('timelineTasks')
            ->orderByRaw("case when status = 'active' then 0 when status = 'draft' then 1 else 2 end")
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(function (ResearchProject $project): array {
                $summary = $this->timelineProgress->projectSummary($project);

                return [
                    'title' => $project->title,
                    'status' => $this->label($project->status),
                    'target_finished_at' => $project->target_finished_at?->toFormattedDateString(),
                    'progress_percentage' => $summary['progress_percentage'],
                    'total_tasks' => $summary['total_tasks'],
                    'completed_tasks' => $summary['completed_tasks'],
                    'timeline_url' => route('admin.projects.timeline.index', ['researchProject' => $project]),
                    'project_url' => route('filament.admin.resources.projects.research-projects.index'),
                ];
            });
    }

    /**
     * @param  Collection<int, string>  $visibleProjectIds
     * @return array<string, mixed>
     */
    private function timelineSummary(Collection $visibleProjectIds): array
    {
        $projectIds = $visibleProjectIds->all();

        if ($projectIds === []) {
            return [
                'active_tasks' => 0,
                'delayed_tasks' => 0,
                'upcoming_tasks' => 0,
                'next_due_task' => null,
            ];
        }

        $activeTaskQuery = ProjectTimelineTask::query()
            ->whereIn('research_project_id', $projectIds)
            ->whereNotIn('status', [ProjectMilestone::STATUS_COMPLETED, ProjectMilestone::STATUS_CANCELLED]);

        $nextDueTask = (clone $activeTaskQuery)
            ->with('project')
            ->whereNotNull('planned_end_date')
            ->whereDate('planned_end_date', '>=', today())
            ->orderBy('planned_end_date')
            ->first();

        return [
            'active_tasks' => (clone $activeTaskQuery)->count(),
            'delayed_tasks' => (clone $activeTaskQuery)
                ->whereNotNull('planned_end_date')
                ->whereDate('planned_end_date', '<', today())
                ->count(),
            'upcoming_tasks' => (clone $activeTaskQuery)
                ->whereNotNull('planned_end_date')
                ->whereDate('planned_end_date', '>=', today())
                ->whereDate('planned_end_date', '<=', today()->addDays(14))
                ->count(),
            'next_due_task' => $nextDueTask ? [
                'title' => $nextDueTask->title,
                'project' => $nextDueTask->project?->title,
                'planned_end_date' => $nextDueTask->planned_end_date?->toFormattedDateString(),
                'url' => route('admin.projects.timeline.index', ['researchProject' => $nextDueTask->research_project_id]),
            ] : null,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function recentDocuments(User $user): Collection
    {
        return Document::query()
            ->visibleTo($user)
            ->with('project')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Document $document): array => [
                'title' => $document->title,
                'project' => $document->project?->title ?? 'No project',
                'status' => $this->label($document->status),
                'updated_at' => $document->updated_at?->diffForHumans(),
                'url' => route('filament.admin.resources.documents.index'),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function recentSurveys(User $user): Collection
    {
        return Survey::query()
            ->visibleTo($user)
            ->with('project')
            ->withCount('responses')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Survey $survey): array => [
                'title' => $survey->title,
                'project' => $survey->project?->title ?? 'No project',
                'status' => $this->label($survey->status),
                'responses_count' => $survey->responses_count,
                'url' => route('admin.surveys.builder.index', ['survey' => $survey]),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function recentAnalysisResults(User $user): Collection
    {
        return $this->analysisResultQuery($user)
            ->with(['project', 'survey'])
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(fn (AnalysisResult $result): array => [
                'title' => $result->title,
                'project' => $result->project?->title ?? 'No project',
                'survey' => $result->survey?->title,
                'updated_at' => $result->updated_at?->diffForHumans(),
                'url' => route('admin.analysis.results.show', ['analysisResult' => $result]),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function pinnedResearchLinks(User $user): Collection
    {
        return ResearchLink::query()
            ->visibleTo($user)
            ->where('is_active', true)
            ->where('is_pinned', true)
            ->orderBy('sort_order')
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (ResearchLink $link): array => [
                'title' => $link->title,
                'category' => ResearchLink::CATEGORY_LABELS[$link->category] ?? $this->label($link->category),
                'domain' => parse_url($link->url, PHP_URL_HOST) ?: 'Unknown domain',
                'url' => $link->url,
            ]);
    }

    /**
     * @param  Collection<int, string>  $visibleProjectIds
     * @return Collection<int, array<string, mixed>>
     */
    private function actionCenterItems(Collection $visibleProjectIds): Collection
    {
        return collect()
            ->merge($this->documentRevisionItems($visibleProjectIds)->take(3)->map(fn (array $item): array => [
                'title' => $item['title'],
                'context' => trim($item['project'].' | '.($item['next_action'] ?: 'Review document status')),
                'badge' => $item['status'],
                'date_label' => $item['due_date'] ? 'Batas revisi: '.$item['due_date'] : null,
                'is_risk' => $item['is_overdue'] || $item['needs_revision'],
                'url' => $item['url'],
                'action_label' => 'Open Documents',
            ]))
            ->merge($this->pendingFollowUps($visibleProjectIds)->take(2)->map(fn (array $item): array => [
                'title' => $item['title'],
                'context' => $item['project'].' | '.$item['session'],
                'badge' => $item['status'],
                'date_label' => $item['due_date'] ? 'Due '.$item['due_date'] : null,
                'is_risk' => $item['is_overdue'],
                'url' => $item['url'],
                'action_label' => 'Open Supervision',
            ]))
            ->merge($this->validationPending($visibleProjectIds)->take(2)->map(fn (array $item): array => [
                'title' => $item['survey'],
                'context' => $item['project'],
                'badge' => $item['progress_label'],
                'date_label' => $item['round_status'],
                'is_risk' => false,
                'url' => $item['url'],
                'action_label' => 'Open Validation',
            ]))
            ->merge($this->recentSupervisionFeedback($visibleProjectIds)->take(2)->map(fn (array $item): array => [
                'title' => $item['title'],
                'context' => $item['project'],
                'badge' => $item['decision'],
                'date_label' => $item['submitted_at'] ? 'Submitted '.$item['submitted_at'] : null,
                'is_risk' => in_array($item['status_key'], [SupervisionSession::STATUS_REVISION_NEEDED, SupervisionSession::STATUS_FEEDBACK_SUBMITTED], true),
                'url' => $item['url'],
                'action_label' => 'Open Supervision',
            ]))
            ->merge($this->timelineRisks($visibleProjectIds)->take(2)->map(fn (array $item): array => [
                'title' => $item['title'],
                'context' => $item['project'],
                'badge' => $item['status'],
                'date_label' => $item['planned_end_date'] ? 'Due '.$item['planned_end_date'] : null,
                'is_risk' => true,
                'url' => $item['url'],
                'action_label' => 'Open Timeline',
            ]))
            ->merge($this->surveysWithoutQuestions($visibleProjectIds)->take(1)->map(fn (array $item): array => [
                'title' => $item['title'],
                'context' => $item['project'],
                'badge' => 'No Questions',
                'date_label' => null,
                'is_risk' => false,
                'url' => $item['url'],
                'action_label' => 'Open Builder',
            ]))
            ->merge($this->projectsWithoutTarget($visibleProjectIds)->take(1)->map(fn (array $item): array => [
                'title' => $item['title'],
                'context' => 'Project setup',
                'badge' => 'No Target',
                'date_label' => null,
                'is_risk' => false,
                'url' => $item['url'],
                'action_label' => 'Open Projects',
            ]))
            ->take(8)
            ->values();
    }

    /**
     * @param  Collection<int, string>  $visibleProjectIds
     * @return Collection<int, array<string, mixed>>
     */
    private function documentRevisionItems(Collection $visibleProjectIds): Collection
    {
        $projectIds = $visibleProjectIds->all();

        if ($projectIds === []) {
            return collect();
        }

        return Document::query()
            ->whereIn('project_id', $projectIds)
            ->where(function (Builder $query): void {
                $query
                    ->where('status', Document::STATUS_REVISION_REQUIRED)
                    ->orWhere('status', Document::STATUS_UNDER_REVIEW)
                    ->orWhere(function (Builder $dueQuery): void {
                        $dueQuery
                            ->whereNotNull('revision_due_date')
                            ->whereDate('revision_due_date', '<', today());
                    });
            })
            ->with('project')
            ->orderByRaw('case when revision_due_date is null then 1 else 0 end')
            ->orderBy('revision_due_date')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Document $document): array => [
                'title' => $document->title,
                'project' => $document->project?->title ?? 'No project',
                'status' => $document->statusLabel(),
                'status_key' => $document->status,
                'due_date' => $document->revision_due_date?->toFormattedDateString(),
                'is_overdue' => $document->isRevisionOverdue(),
                'needs_revision' => $document->needsRevision(),
                'next_action' => $document->next_action,
                'url' => route('filament.admin.resources.documents.index'),
            ]);
    }

    /**
     * @param  Collection<int, string>  $visibleProjectIds
     * @return Collection<int, array<string, mixed>>
     */
    private function pendingFollowUps(Collection $visibleProjectIds): Collection
    {
        $projectIds = $visibleProjectIds->all();

        if ($projectIds === []) {
            return collect();
        }

        return SupervisionFollowUpItem::query()
            ->whereHas('session', fn (Builder $query) => $query->whereIn('research_project_id', $projectIds))
            ->whereIn('status', [
                SupervisionFollowUpItem::STATUS_TODO,
                SupervisionFollowUpItem::STATUS_IN_PROGRESS,
                SupervisionFollowUpItem::STATUS_WAITING_SUPERVISOR,
            ])
            ->with(['session.project'])
            ->orderByRaw('case when due_date is null then 1 else 0 end')
            ->orderBy('due_date')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (SupervisionFollowUpItem $item): array => [
                'title' => $item->title,
                'project' => $item->session?->project?->title ?? 'No project',
                'session' => $item->session?->title ?? 'No session',
                'status' => SupervisionFollowUpItem::STATUS_LABELS[$item->status] ?? $this->label($item->status),
                'priority' => SupervisionFollowUpItem::PRIORITY_LABELS[$item->priority] ?? $this->label($item->priority),
                'due_date' => $item->due_date?->toFormattedDateString(),
                'is_overdue' => $item->due_date !== null && $item->due_date->isBefore(today()),
                'url' => $item->session?->project
                    ? route('admin.projects.supervision.index', ['researchProject' => $item->session->project])
                    : route('filament.admin.resources.projects.research-projects.index'),
            ]);
    }

    /**
     * @param  Collection<int, string>  $visibleProjectIds
     * @return Collection<int, array<string, mixed>>
     */
    private function validationPending(Collection $visibleProjectIds): Collection
    {
        $projectIds = $visibleProjectIds->all();

        if ($projectIds === []) {
            return collect();
        }

        return SurveyValidationRound::query()
            ->whereIn('research_project_id', $projectIds)
            ->whereHas('assignments', fn (Builder $query) => $query->whereIn('status', [
                SurveyValidationAssignment::STATUS_PENDING,
                SurveyValidationAssignment::STATUS_LINK_GENERATED,
                SurveyValidationAssignment::STATUS_OPENED,
                SurveyValidationAssignment::STATUS_EXPIRED,
                SurveyValidationAssignment::STATUS_REVOKED,
            ]))
            ->with(['project', 'survey'])
            ->withCount([
                'assignments as total_assignments_count',
                'assignments as submitted_assignments_count' => fn (Builder $query) => $query->where('status', SurveyValidationAssignment::STATUS_SUBMITTED),
            ])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (SurveyValidationRound $round): array => [
                'survey' => $round->survey?->title ?? $round->title,
                'round' => $round->title,
                'project' => $round->project?->title ?? 'No project',
                'round_status' => SurveyValidationRound::STATUS_LABELS[$round->status] ?? $this->label($round->status),
                'progress_label' => $round->submitted_assignments_count.' / '.$round->total_assignments_count.' submitted',
                'url' => $round->survey
                    ? route('admin.surveys.validation.index', ['survey' => $round->survey])
                    : route('filament.admin.resources.surveys.index'),
            ]);
    }

    /**
     * @param  Collection<int, string>  $visibleProjectIds
     * @return Collection<int, array<string, mixed>>
     */
    private function recentSupervisionFeedback(Collection $visibleProjectIds): Collection
    {
        $projectIds = $visibleProjectIds->all();

        if ($projectIds === []) {
            return collect();
        }

        return SupervisionSession::query()
            ->whereIn('research_project_id', $projectIds)
            ->whereIn('status', [
                SupervisionSession::STATUS_FEEDBACK_SUBMITTED,
                SupervisionSession::STATUS_REVISION_NEEDED,
                SupervisionSession::STATUS_OPENED,
            ])
            ->whereHas('feedback')
            ->with(['project', 'feedback' => fn ($query) => $query->latest()])
            ->latest('submitted_at')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(function (SupervisionSession $session): array {
                $feedback = $session->feedback->first();

                return [
                    'title' => $session->title,
                    'project' => $session->project?->title ?? 'No project',
                    'status' => SupervisionSession::STATUS_LABELS[$session->status] ?? $this->label($session->status),
                    'status_key' => $session->status,
                    'decision' => $feedback
                        ? (SupervisionFeedback::DECISION_LABELS[$feedback->decision] ?? $this->label($feedback->decision))
                        : 'Feedback',
                    'submitted_at' => $session->submitted_at?->toFormattedDateString() ?? $feedback?->created_at?->toFormattedDateString(),
                    'url' => $session->project
                        ? route('admin.projects.supervision.index', ['researchProject' => $session->project])
                        : route('filament.admin.resources.projects.research-projects.index'),
                ];
            });
    }

    /**
     * @param  Collection<int, string>  $visibleProjectIds
     * @return Collection<int, array<string, mixed>>
     */
    private function timelineRisks(Collection $visibleProjectIds): Collection
    {
        $projectIds = $visibleProjectIds->all();

        if ($projectIds === []) {
            return collect();
        }

        return ProjectTimelineTask::query()
            ->whereIn('research_project_id', $projectIds)
            ->whereNotIn('status', [ProjectMilestone::STATUS_COMPLETED, ProjectMilestone::STATUS_CANCELLED])
            ->whereNotNull('planned_end_date')
            ->whereDate('planned_end_date', '<', today())
            ->with(['project', 'milestone'])
            ->orderBy('planned_end_date')
            ->limit(5)
            ->get()
            ->map(fn (ProjectTimelineTask $task): array => [
                'title' => $task->title,
                'project' => $task->project?->title ?? 'No project',
                'milestone' => $task->milestone?->title,
                'planned_end_date' => $task->planned_end_date?->toFormattedDateString(),
                'status' => $this->label($task->status),
                'url' => $task->project
                    ? route('admin.projects.timeline.index', ['researchProject' => $task->project])
                    : route('filament.admin.resources.projects.research-projects.index'),
            ]);
    }

    /**
     * @param  Collection<int, string>  $visibleProjectIds
     * @return Collection<int, array<string, mixed>>
     */
    private function surveysWithoutQuestions(Collection $visibleProjectIds): Collection
    {
        $projectIds = $visibleProjectIds->all();

        if ($projectIds === []) {
            return collect();
        }

        return Survey::query()
            ->whereIn('project_id', $projectIds)
            ->with('project')
            ->whereDoesntHave('questions')
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(fn (Survey $survey): array => [
                'title' => $survey->title,
                'project' => $survey->project?->title ?? 'No project',
                'url' => route('admin.surveys.builder.index', ['survey' => $survey]),
            ]);
    }

    /**
     * @param  Collection<int, string>  $visibleProjectIds
     * @return Collection<int, array<string, mixed>>
     */
    private function projectsWithoutTarget(Collection $visibleProjectIds): Collection
    {
        $projectIds = $visibleProjectIds->all();

        if ($projectIds === []) {
            return collect();
        }

        return ResearchProject::query()
            ->whereIn('id', $projectIds)
            ->whereNull('target_finished_at')
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(fn (ResearchProject $project): array => [
                'title' => $project->title,
                'url' => route('filament.admin.resources.projects.research-projects.index'),
            ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function quickActions(): array
    {
        return [
            [
                'label' => 'Open Projects',
                'description' => 'Manage research workspaces and project timelines.',
                'url' => route('filament.admin.resources.projects.research-projects.index'),
                'initial' => 'P',
            ],
            [
                'label' => 'Open Documents',
                'description' => 'Review document metadata, versions, and review links.',
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
                'label' => 'Open Research Links',
                'description' => 'Access pinned journals, datasets, repositories, and OJS pages.',
                'url' => route('filament.admin.resources.research-links.index'),
                'initial' => 'L',
            ],
            [
                'label' => 'Google Drive Settings',
                'description' => 'Connect Drive before storing research files.',
                'url' => route('filament.admin.pages.settings.google-drive'),
                'initial' => 'G',
            ],
        ];
    }

    private function driveConnected(User $user): bool
    {
        return $user->googleDriveConnection()
            ->where('status', DriveConnection::STATUS_CONNECTED)
            ->exists();
    }

    private function analysisResultQuery(User $user): Builder
    {
        $query = AnalysisResult::query();

        if ($user->hasRole('super_admin')) {
            return $query;
        }

        return $query->whereHas(
            'project',
            fn (Builder $projectQuery) => $projectQuery->where('owner_id', $user->getKey()),
        );
    }

    private function label(?string $value): string
    {
        return Str::headline((string) $value);
    }
}
