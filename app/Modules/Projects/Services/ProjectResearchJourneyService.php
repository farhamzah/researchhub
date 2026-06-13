<?php

namespace App\Modules\Projects\Services;

use App\Models\Document;
use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Models\ResearchProject;
use App\Models\SupervisionFollowUpItem;
use App\Models\SupervisionSession;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProjectResearchJourneyService
{
    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_NEEDS_ATTENTION = 'needs_attention';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_BLOCKED = 'blocked';

    /**
     * @return array<string, mixed>
     */
    public function build(ResearchProject $project): array
    {
        $project->loadMissing([
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
        ]);

        $steps = collect([
            $this->projectSetupStep($project),
            $this->documentsStep($project),
            $this->timelineStep($project),
            $this->surveyInstrumentStep($project),
            $this->scoringStep($project),
            $this->expertValidationStep($project),
            $this->validationResultsStep($project),
            $this->responsesAnalysisStep($project),
            $this->supervisionStep($project),
            $this->followUpStep($project),
            $this->reportPublicationStep($project),
        ]);
        $progress = $this->progressPercentage($steps);
        $nextStep = $this->nextStep($steps);

        return [
            'project' => [
                'title' => $project->title,
                'status' => $this->label($project->status),
                'target_finished_at' => $project->target_finished_at?->toFormattedDateString(),
            ],
            'progress_percentage' => $progress,
            'completed_count' => $steps->where('status', self::STATUS_COMPLETED)->count(),
            'attention_count' => $steps->whereIn('status', [self::STATUS_NEEDS_ATTENTION, self::STATUS_BLOCKED])->count(),
            'next_step' => $nextStep,
            'steps' => $steps->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(ResearchProject $project): array
    {
        $journey = $this->build($project);

        return [
            'project_id' => $project->getKey(),
            'title' => $project->title,
            'status' => $this->label($project->status),
            'progress_percentage' => $journey['progress_percentage'],
            'next_step' => $journey['next_step'],
            'url' => route('admin.projects.journey.show', ['researchProject' => $project]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectSetupStep(ResearchProject $project): array
    {
        $hasCoreSetup = filled($project->title) && filled($project->owner_id) && filled($project->status);
        $hasDateTarget = $project->started_at !== null || $project->target_finished_at !== null;

        if (! $hasCoreSetup) {
            return $this->step(
                'project_setup',
                'Setup Project',
                self::STATUS_BLOCKED,
                'Data dasar project belum lengkap. Lengkapi judul, pemilik, dan status agar alur riset bisa dipantau.',
                'Lengkapi Project',
                route('filament.admin.resources.projects.research-projects.index'),
                ['Status' => 'Belum lengkap'],
            );
        }

        if ($project->target_finished_at === null) {
            return $this->step(
                'project_setup',
                'Setup Project',
                self::STATUS_NEEDS_ATTENTION,
                'Project sudah dibuat, tetapi target selesai belum diisi. Target membantu MyRiset memberi arahan langkah berikutnya.',
                'Lengkapi Target',
                route('filament.admin.resources.projects.research-projects.index'),
                ['Target selesai' => 'Belum diisi'],
            );
        }

        return $this->step(
            'project_setup',
            'Setup Project',
            $hasDateTarget ? self::STATUS_COMPLETED : self::STATUS_IN_PROGRESS,
            'Identitas project dan target riset sudah siap untuk dipakai sebagai ruang kerja utama.',
            'Buka Project',
            route('filament.admin.resources.projects.research-projects.index'),
            ['Target selesai' => $project->target_finished_at->toFormattedDateString()],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function documentsStep(ResearchProject $project): array
    {
        $documents = $project->documents;
        $documentCount = $documents->count();
        $approvedCount = $documents
            ->whereIn('status', [Document::STATUS_APPROVED, Document::STATUS_FINAL])
            ->count();
        $underReviewCount = $documents
            ->where('status', Document::STATUS_UNDER_REVIEW)
            ->count();
        $revisionNeededCount = $documents
            ->where('status', Document::STATUS_REVISION_REQUIRED)
            ->count();
        $overdueRevisionCount = $documents
            ->filter(fn (Document $document): bool => $document->isRevisionOverdue())
            ->count();
        $draftCount = $documents
            ->where('status', Document::STATUS_DRAFT)
            ->count();
        $coreReadyCount = $documents
            ->whereIn('document_type', [
                Document::TYPE_PROPOSAL,
                Document::TYPE_CHAPTER_1,
                Document::TYPE_CHAPTER_2,
                Document::TYPE_CHAPTER_3,
                Document::TYPE_INSTRUMENT,
            ])
            ->whereIn('status', [Document::STATUS_APPROVED, Document::STATUS_FINAL, Document::STATUS_UNDER_REVIEW])
            ->count();

        $metrics = [
            'Total documents' => $documentCount,
            'Approved documents' => $approvedCount,
            'Revision-needed documents' => $revisionNeededCount,
            'Under-review documents' => $underReviewCount,
            'Overdue revisions' => $overdueRevisionCount,
        ];

        if ($documentCount === 0) {
            return $this->step(
                'documents',
                'Dokumen Riset',
                self::STATUS_NOT_STARTED,
                'Belum ada dokumen. Tambahkan proposal, bab, instrumen, atau draft artikel agar project punya bahan kerja.',
                'Buka Dokumen',
                route('filament.admin.resources.documents.index'),
                $metrics,
            );
        }

        if ($revisionNeededCount > 0 || $overdueRevisionCount > 0) {
            return $this->step(
                'documents',
                'Dokumen Riset',
                self::STATUS_NEEDS_ATTENTION,
                'Ada dokumen akademik yang membutuhkan revisi atau sudah melewati batas revisi. Selesaikan next action dokumen sebelum lanjut ke validasi, bimbingan, atau pelaporan.',
                'Periksa Revisi Dokumen',
                route('filament.admin.resources.documents.index'),
                $metrics,
            );
        }

        if ($draftCount === $documentCount) {
            return $this->step(
                'documents',
                'Dokumen Riset',
                self::STATUS_IN_PROGRESS,
                'Dokumen riset sudah dibuat, tetapi semuanya masih draft. Tandai versi yang sedang direview atau sudah approved agar progres akademik lebih jelas.',
                'Lengkapi Status Dokumen',
                route('filament.admin.resources.documents.index'),
                $metrics,
            );
        }

        return $this->step(
            'documents',
            'Dokumen Riset',
            $coreReadyCount > 0 ? self::STATUS_COMPLETED : self::STATUS_IN_PROGRESS,
            $coreReadyCount > 0
                ? 'Dokumen inti riset sudah memiliki versi approved atau under review. Vault dokumen dapat dipakai untuk menjaga status, versi, dan next action akademik tetap rapi.'
                : 'Dokumen riset sudah tersedia, tetapi tipe/status akademiknya belum cukup jelas untuk membaca kesiapan proposal, bab, instrumen, atau artikel.',
            'Buka Dokumen',
            route('filament.admin.resources.documents.index'),
            $metrics,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function timelineStep(ResearchProject $project): array
    {
        $taskCount = $project->timelineTasks->count();
        $milestoneCount = $project->milestones->count();
        $activeTasks = $project->timelineTasks->reject(fn (ProjectTimelineTask $task): bool => in_array($task->status, [
            ProjectMilestone::STATUS_COMPLETED,
            ProjectMilestone::STATUS_CANCELLED,
        ], true));
        $overdueCount = $activeTasks
            ->filter(fn (ProjectTimelineTask $task): bool => $task->planned_end_date !== null && $task->planned_end_date->isBefore(today()))
            ->count();

        if ($overdueCount > 0) {
            return $this->step(
                'timeline',
                'Timeline Riset',
                self::STATUS_NEEDS_ATTENTION,
                'Timeline memiliki tugas terlambat. Periksa deadline dan tentukan tindak lanjut agar progres riset kembali terkendali.',
                'Periksa Timeline',
                route('admin.projects.timeline.index', ['researchProject' => $project]),
                ['Milestone' => $milestoneCount, 'Tugas' => $taskCount, 'Terlambat' => $overdueCount],
            );
        }

        if ($taskCount > 0 || $milestoneCount > 0) {
            $completedCount = $project->timelineTasks
                ->where('status', ProjectMilestone::STATUS_COMPLETED)
                ->count();

            return $this->step(
                'timeline',
                'Timeline Riset',
                $completedCount > 0 && $completedCount === $taskCount ? self::STATUS_COMPLETED : self::STATUS_IN_PROGRESS,
                'Timeline sudah dibuat. Gunakan halaman ini untuk memantau milestone, tugas aktif, dan progres riset.',
                'Buka Timeline',
                route('admin.projects.timeline.index', ['researchProject' => $project]),
                ['Milestone' => $milestoneCount, 'Tugas' => $taskCount, 'Selesai' => $completedCount],
            );
        }

        return $this->step(
            'timeline',
            'Timeline Riset',
            self::STATUS_NEEDS_ATTENTION,
            'Belum ada timeline. Buat milestone agar riset tidak hanya berjalan sebagai daftar file dan catatan.',
            'Buat Timeline',
            route('admin.projects.timeline.index', ['researchProject' => $project]),
            ['Milestone' => 0, 'Tugas' => 0],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function surveyInstrumentStep(ResearchProject $project): array
    {
        $surveys = $project->surveys;
        $survey = $this->primarySurvey($project);
        $questionCount = $surveys->sum(fn (Survey $survey): int => $survey->questions->count());

        if ($surveys->isEmpty()) {
            return $this->step(
                'survey_instrument',
                'Instrumen Survey',
                self::STATUS_NOT_STARTED,
                'Belum ada instrumen survey. Buat survey saat project sudah membutuhkan pengumpulan data.',
                'Buka Survey',
                route('filament.admin.resources.surveys.index'),
                ['Survey' => 0, 'Pertanyaan' => 0],
            );
        }

        if ($questionCount === 0) {
            return $this->step(
                'survey_instrument',
                'Instrumen Survey',
                self::STATUS_NEEDS_ATTENTION,
                'Survey sudah dibuat, tetapi belum punya pertanyaan. Tambahkan butir agar instrumen siap diuji dan divalidasi.',
                'Buka Builder',
                $survey ? route('admin.surveys.builder.index', ['survey' => $survey]) : route('filament.admin.resources.surveys.index'),
                ['Survey' => $surveys->count(), 'Pertanyaan' => 0],
            );
        }

        return $this->step(
            'survey_instrument',
            'Instrumen Survey',
            self::STATUS_COMPLETED,
            'Instrumen survey sudah memiliki pertanyaan dan siap masuk ke skoring, validasi ahli, atau pengumpulan respons.',
            'Buka Builder',
            $survey ? route('admin.surveys.builder.index', ['survey' => $survey]) : route('filament.admin.resources.surveys.index'),
            ['Survey' => $surveys->count(), 'Pertanyaan' => $questionCount],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function scoringStep(ResearchProject $project): array
    {
        $survey = $this->primarySurvey($project);

        if (! $survey) {
            return $this->step(
                'scoring_indicators',
                'Skoring & Indikator',
                self::STATUS_NOT_STARTED,
                'Skoring bisa disiapkan setelah survey dibuat.',
                'Buka Survey',
                route('filament.admin.resources.surveys.index'),
                ['Indikator' => 0, 'Mapping' => 0],
            );
        }

        $likertCount = $survey->questions
            ->where('type', SurveyQuestion::TYPE_LIKERT)
            ->count();
        $indicatorCount = $survey->indicators->count();
        $scoredCount = $survey->questionScorings
            ->where('is_scored', true)
            ->count();

        if ($likertCount === 0) {
            return $this->step(
                'scoring_indicators',
                'Skoring & Indikator',
                self::STATUS_IN_PROGRESS,
                'Survey belum memiliki butir Likert atau butir berskor. Skoring dapat dikonfigurasi saat instrumen membutuhkan analisis indikator.',
                'Buka Skoring',
                route('admin.surveys.scoring.index', ['survey' => $survey]),
                ['Indikator' => $indicatorCount, 'Mapping' => $scoredCount],
            );
        }

        if ($indicatorCount > 0 && $scoredCount >= $likertCount) {
            return $this->step(
                'scoring_indicators',
                'Skoring & Indikator',
                self::STATUS_COMPLETED,
                'Indikator dan mapping skoring sudah siap untuk membantu ringkasan analisis.',
                'Buka Skoring',
                route('admin.surveys.scoring.index', ['survey' => $survey]),
                ['Indikator' => $indicatorCount, 'Mapping' => $scoredCount],
            );
        }

        return $this->step(
            'scoring_indicators',
            'Skoring & Indikator',
            $indicatorCount > 0 && $scoredCount > 0 ? self::STATUS_IN_PROGRESS : self::STATUS_NEEDS_ATTENTION,
            'Survey memiliki butir Likert, tetapi indikator atau mapping skoring belum lengkap. Lengkapi agar hasil analisis lebih bermakna.',
            'Lengkapi Skoring',
            route('admin.surveys.scoring.index', ['survey' => $survey]),
            ['Indikator' => $indicatorCount, 'Mapping' => $scoredCount, 'Butir Likert' => $likertCount],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function expertValidationStep(ResearchProject $project): array
    {
        $survey = $this->primarySurvey($project);

        if (! $survey) {
            return $this->step(
                'expert_validation',
                'Validasi Ahli',
                self::STATUS_NOT_STARTED,
                'Validasi ahli bisa dimulai setelah instrumen survey tersedia.',
                'Buka Survey',
                route('filament.admin.resources.surveys.index'),
                ['Round' => 0],
            );
        }

        $rounds = $survey->validationRounds;
        $round = $this->primaryValidationRound($project);

        if ($rounds->isEmpty()) {
            return $this->step(
                'expert_validation',
                'Validasi Ahli',
                self::STATUS_NEEDS_ATTENTION,
                'Instrumen survey sudah ada, tetapi belum divalidasi ahli. Mulai validasi agar instrumen dapat dipertanggungjawabkan secara akademik.',
                'Mulai Validasi',
                route('admin.surveys.validation.index', ['survey' => $survey]),
                ['Round' => 0],
            );
        }

        $assignmentCount = $round?->assignments->count() ?? 0;
        $submittedCount = $round?->assignments
            ->where('status', SurveyValidationAssignment::STATUS_SUBMITTED)
            ->count() ?? 0;

        return $this->step(
            'expert_validation',
            'Validasi Ahli',
            $assignmentCount > 0 && $submittedCount === $assignmentCount ? self::STATUS_COMPLETED : self::STATUS_IN_PROGRESS,
            $submittedCount === $assignmentCount && $assignmentCount > 0
                ? 'Semua assignment validasi ahli sudah submitted.'
                : 'Validasi ahli sedang berjalan. Pantau assignment yang belum submitted.',
            'Buka Validasi',
            route('admin.surveys.validation.index', ['survey' => $survey]),
            ['Round' => $rounds->count(), 'Submitted' => "{$submittedCount} / {$assignmentCount}"],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validationResultsStep(ResearchProject $project): array
    {
        $survey = $this->primarySurvey($project);
        $round = $this->primaryValidationRound($project);

        if (! $survey || ! $round) {
            return $this->step(
                'validation_results',
                'Hasil Validasi',
                self::STATUS_NOT_STARTED,
                'Hasil validasi akan tersedia setelah round validasi ahli dibuat dan validator mengirimkan skor.',
                'Buka Validasi',
                $survey ? route('admin.surveys.validation.index', ['survey' => $survey]) : route('filament.admin.resources.surveys.index'),
                ['Skor' => 0],
            );
        }

        $submittedCount = $round->assignments
            ->where('status', SurveyValidationAssignment::STATUS_SUBMITTED)
            ->count();
        $scoreCount = $round->assignments->sum(fn (SurveyValidationAssignment $assignment): int => $assignment->scores->count());

        if ($submittedCount === 0) {
            return $this->step(
                'validation_results',
                'Hasil Validasi',
                self::STATUS_NEEDS_ATTENTION,
                'Round validasi sudah ada, tetapi belum ada validator yang submitted. Tunggu submission atau follow up validator.',
                'Buka Validasi',
                route('admin.surveys.validation.index', ['survey' => $survey]),
                ['Submitted' => 0, 'Skor' => 0],
            );
        }

        return $this->step(
            'validation_results',
            'Hasil Validasi',
            $scoreCount > 0 ? self::STATUS_COMPLETED : self::STATUS_IN_PROGRESS,
            'Skor validasi sudah tersedia dan bisa dibaca sebagai ringkasan Aiken/CVI-ready.',
            'Lihat Hasil',
            route('admin.surveys.validation.results.show', ['survey' => $survey, 'round' => $round]),
            ['Submitted' => $submittedCount, 'Skor' => $scoreCount],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responsesAnalysisStep(ResearchProject $project): array
    {
        $survey = $this->primarySurvey($project);

        if (! $survey) {
            return $this->step(
                'responses_analysis',
                'Respons & Analisis',
                self::STATUS_NOT_STARTED,
                'Respons dan analisis bisa dimulai setelah instrumen survey tersedia.',
                'Buka Survey',
                route('filament.admin.resources.surveys.index'),
                ['Respons' => 0, 'Analisis' => 0],
            );
        }

        $responseCount = $survey->responses
            ->where('status', 'submitted')
            ->count();
        $analysisCount = $survey->analysisResults->count();

        if ($responseCount > 0 && $analysisCount > 0) {
            return $this->step(
                'responses_analysis',
                'Respons & Analisis',
                self::STATUS_COMPLETED,
                'Respons demo sudah tersedia dan hasil analisis deskriptif sudah dibuat.',
                'Buka Analisis',
                route('admin.surveys.analysis.index', ['survey' => $survey]),
                ['Respons' => $responseCount, 'Analisis' => $analysisCount],
            );
        }

        if ($responseCount > 0) {
            return $this->step(
                'responses_analysis',
                'Respons & Analisis',
                self::STATUS_IN_PROGRESS,
                'Respons sudah masuk, tetapi analisis belum dibuat. Jalankan analisis deskriptif untuk membuat ringkasan akademik.',
                'Jalankan Analisis',
                route('admin.surveys.analysis.index', ['survey' => $survey]),
                ['Respons' => $responseCount, 'Analisis' => 0],
            );
        }

        return $this->step(
            'responses_analysis',
            'Respons & Analisis',
            self::STATUS_NOT_STARTED,
            'Belum ada respons submitted. Publikasikan instrumen saat siap dan kumpulkan data secara aman.',
            'Buka Respons',
            route('admin.surveys.responses.index', ['survey' => $survey]),
            ['Respons' => 0, 'Analisis' => $analysisCount],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function supervisionStep(ResearchProject $project): array
    {
        $sessionCount = $project->supervisionSessions->count();

        return $this->step(
            'supervision',
            'Bimbingan',
            $sessionCount > 0 ? self::STATUS_IN_PROGRESS : self::STATUS_NEEDS_ATTENTION,
            $sessionCount > 0
                ? 'Catatan bimbingan sudah tersedia. Gunakan halaman bimbingan untuk menyatukan feedback, resources, dan tindak lanjut.'
                : 'Belum ada catatan bimbingan. Tambahkan sesi agar arahan pembimbing dan revisi tidak tersebar.',
            'Buka Bimbingan',
            route('admin.projects.supervision.index', ['researchProject' => $project]),
            ['Sesi' => $sessionCount],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function followUpStep(ResearchProject $project): array
    {
        $followUps = $project->supervisionSessions
            ->flatMap(fn (SupervisionSession $session): Collection => $session->followUpItems);
        $pendingCount = $followUps
            ->whereIn('status', [
                SupervisionFollowUpItem::STATUS_TODO,
                SupervisionFollowUpItem::STATUS_IN_PROGRESS,
                SupervisionFollowUpItem::STATUS_WAITING_SUPERVISOR,
            ])
            ->count();

        if ($followUps->isEmpty()) {
            return $this->step(
                'follow_up_revisions',
                'Tindak Lanjut Revisi',
                self::STATUS_NOT_STARTED,
                'Belum ada item tindak lanjut. Saat bimbingan menghasilkan revisi, catat di sini agar tidak hilang.',
                'Buka Bimbingan',
                route('admin.projects.supervision.index', ['researchProject' => $project]),
                ['Tindak lanjut' => 0],
            );
        }

        return $this->step(
            'follow_up_revisions',
            'Tindak Lanjut Revisi',
            $pendingCount > 0 ? self::STATUS_NEEDS_ATTENTION : self::STATUS_COMPLETED,
            $pendingCount > 0
                ? 'Masih ada tindak lanjut revisi yang perlu diselesaikan. Jadikan ini prioritas sebelum lanjut ke laporan.'
                : 'Semua tindak lanjut revisi sudah selesai atau dibatalkan.',
            'Buka Tindak Lanjut',
            route('admin.projects.supervision.index', ['researchProject' => $project]),
            ['Total' => $followUps->count(), 'Pending' => $pendingCount],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function reportPublicationStep(ResearchProject $project): array
    {
        $reportDocuments = $project->documents
            ->filter(function (Document $document): bool {
                $text = Str::lower(collect([
                    $document->title,
                    $document->description,
                    $document->category?->name,
                    $document->category?->slug,
                    implode(' ', $document->tags ?? []),
                ])->filter()->join(' '));

                return Str::contains($text, ['report', 'laporan', 'publication', 'publikasi', 'article', 'artikel', 'manuscript', 'jurnal', 'journal']);
            });
        $analysisCount = $project->analysisResults->count();

        if ($reportDocuments->isNotEmpty() || $analysisCount > 0) {
            $finalCount = $reportDocuments
                ->whereIn('status', [Document::STATUS_APPROVED, Document::STATUS_FINAL])
                ->count();

            return $this->step(
                'report_publication',
                'Laporan / Publikasi',
                $finalCount > 0 ? self::STATUS_COMPLETED : self::STATUS_IN_PROGRESS,
                'Bahan laporan atau publikasi sudah mulai tersedia. Review bersama pembimbing sebelum dipakai sebagai dokumen akademik resmi.',
                'Buka Dokumen',
                route('filament.admin.resources.documents.index'),
                ['Dokumen laporan' => $reportDocuments->count(), 'Analisis' => $analysisCount],
            );
        }

        return $this->step(
            'report_publication',
            'Laporan / Publikasi',
            self::STATUS_NEEDS_ATTENTION,
            'Belum ada draft laporan, artikel, atau hasil analisis. Siapkan bahan publikasi setelah data dan revisi utama terkendali.',
            'Siapkan Draft',
            route('filament.admin.resources.documents.index'),
            ['Dokumen laporan' => 0, 'Analisis' => 0],
        );
    }

    private function primarySurvey(ResearchProject $project): ?Survey
    {
        return $project->surveys
            ->sortByDesc(fn (Survey $survey): int => $survey->questions->count())
            ->first();
    }

    private function primaryValidationRound(ResearchProject $project): ?SurveyValidationRound
    {
        return $project->surveys
            ->flatMap(fn (Survey $survey): Collection => $survey->validationRounds)
            ->sortByDesc(fn (SurveyValidationRound $round): int => $round->assignments->count())
            ->first();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $steps
     */
    private function progressPercentage(Collection $steps): int
    {
        $score = $steps->sum(fn (array $step): float => match ($step['status']) {
            self::STATUS_COMPLETED => 1.0,
            self::STATUS_IN_PROGRESS => 0.5,
            default => 0.0,
        });

        return (int) round(($score / max(1, $steps->count())) * 100);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $steps
     * @return array<string, mixed>
     */
    private function nextStep(Collection $steps): array
    {
        return $steps->firstWhere('status', self::STATUS_NEEDS_ATTENTION)
            ?? $steps->firstWhere('status', self::STATUS_BLOCKED)
            ?? $steps->firstWhere('status', self::STATUS_IN_PROGRESS)
            ?? $steps->firstWhere('status', self::STATUS_NOT_STARTED)
            ?? [
                'key' => 'complete',
                'label' => 'Riset Siap Dilanjutkan',
                'status' => self::STATUS_COMPLETED,
                'description' => 'Semua langkah utama sudah terlihat rapi. Lanjutkan review akademik dan publikasi.',
                'action_label' => 'Buka Project',
                'action_url' => route('filament.admin.resources.projects.research-projects.index'),
                'metrics' => [],
            ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    private function step(
        string $key,
        string $label,
        string $status,
        string $description,
        string $actionLabel,
        string $actionUrl,
        array $metrics = [],
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'description' => $description,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'metrics' => $metrics,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_IN_PROGRESS => 'Sedang berjalan',
            self::STATUS_NEEDS_ATTENTION => 'Perlu perhatian',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_BLOCKED => 'Terhambat',
            default => 'Belum mulai',
        };
    }

    private function label(?string $value): string
    {
        return Str::headline((string) $value);
    }
}
