<?php

namespace App\Modules\Surveys\Actions;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveySupervisorReviewer;
use App\Models\SurveySupervisorReviewerHub;
use App\Models\SurveySupervisorReviewRound;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\SupervisorReviews\Actions\GenerateSupervisorReviewerHubLinkAction;
use App\Modules\SupervisorReviews\Services\SupervisorReviewSnapshotService;
use App\Modules\Surveys\Services\PharmVrInstrumentV2Catalog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class FinalizePharmVrInstrumentsV2ForSupervisorReviewAction
{
    private const EXPECTED_COUNTS = [
        PharmVrInstrumentV2Catalog::STUDENT => 34,
        PharmVrInstrumentV2Catalog::LECTURER => 35,
        PharmVrInstrumentV2Catalog::PRACTITIONER => 26,
    ];

    public function __construct(
        private readonly PharmVrInstrumentV2Catalog $catalog,
        private readonly SupervisorReviewSnapshotService $snapshots,
        private readonly GenerateSupervisorReviewerHubLinkAction $generateHub,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /** @return array{output_path: string, sha256: string, instruments: int, questions: array<string, int>, old_hubs_revoked: int, new_hubs_created: int} */
    public function handle(
        User $actor,
        ResearchProject $project,
        string $contact,
        CarbonInterface $expiresAt,
        string $outputPath,
    ): array {
        $this->guardOutputPath($outputPath);

        try {
            return DB::transaction(function () use ($actor, $project, $contact, $expiresAt, $outputPath): array {
                $definitions = collect($this->catalog->instruments($contact))->keyBy('code');
                $surveys = Survey::query()
                    ->where('project_id', $project->getKey())
                    ->where('instrument_version', PharmVrInstrumentV2Catalog::VERSION)
                    ->whereIn('instrument_code', array_keys(self::EXPECTED_COUNTS))
                    ->with(['pages.questions', 'supervisorReviewRounds.reviewers'])
                    ->withCount('responses')
                    ->lockForUpdate()
                    ->orderBy('instrument_code')
                    ->get();

                if ($surveys->count() !== 3) {
                    throw new RuntimeException('Harus ditemukan tepat tiga instrumen PharmVR v2.0.');
                }
                $reviewers = collect();
                $questionCounts = [];

                foreach ($surveys as $survey) {
                    $this->guardSurvey($survey);
                    $definition = $definitions->get($survey->instrument_code);
                    if (! is_array($definition)) {
                        throw new RuntimeException('Definisi instrumen tidak ditemukan: '.$survey->instrument_code);
                    }

                    $expectedQuestions = collect($definition['pages'])->flatMap(fn (array $page): array => $page['questions'])->keyBy('key');
                    $actualQuestions = $survey->questions->keyBy('question_key');
                    if ($actualQuestions->keys()->sort()->values()->all() !== $expectedQuestions->keys()->sort()->values()->all()) {
                        throw new RuntimeException('Stable code tidak identik: '.$survey->instrument_code);
                    }

                    foreach ($expectedQuestions as $key => $expected) {
                        $question = $actualQuestions->get($key);
                        if ($question->type !== $expected['type']
                            || ($question->options ?? null) !== ($expected['options'] === null ? null : ['choices' => $expected['options']])
                            || ($question->settings ?? null) !== ($expected['settings'] ?? null)) {
                            throw new RuntimeException('Tipe, opsi, atau setting berbeda pada '.$key.'.');
                        }
                        if ($question->label !== $expected['label']) {
                            $question->forceFill(['label' => $expected['label']])->save();
                        }
                    }

                    $round = $survey->supervisorReviewRounds->sole();
                    $this->guardRound($round);
                    $oldHash = $round->snapshot_hash;
                    $snapshot = $this->snapshots->snapshot($survey->fresh(['project', 'pages.questions.scoring.indicator', 'questions.scoring.indicator']));
                    $newHash = $this->snapshots->hash($snapshot);
                    $round->forceFill([
                        'snapshot_json' => $snapshot,
                        'snapshot_hash' => $newHash,
                        'snapshot_taken_at' => now(),
                    ])->save();

                    $this->activityLogger->log('survey.pharmvr_v2.supervisor_snapshot_replaced_before_open', $actor, $project, $survey, [
                        'instrument_code' => $survey->instrument_code,
                        'old_snapshot_hash' => $oldHash,
                        'new_snapshot_hash' => $newHash,
                    ]);
                    $reviewers = $reviewers->merge($round->reviewers);
                    $questionCounts[$survey->instrument_code] = $actualQuestions->count();
                }

                if ($reviewers->count() !== 9 || $reviewers->groupBy('supervisor_code')->count() !== 3) {
                    throw new RuntimeException('Assignment harus tepat 3 pembimbing × 3 instrumen.');
                }

                $oldHubs = SurveySupervisorReviewerHub::query()
                    ->where('research_project_id', $project->getKey())
                    ->whereNull('revoked_at')
                    ->whereNotNull('token_hash')
                    ->lockForUpdate()
                    ->get();
                if ($oldHubs->count() !== 3 || $oldHubs->contains(fn (SurveySupervisorReviewerHub $hub): bool => $hub->opened_at !== null)) {
                    throw new RuntimeException('Tautan Hub aktif harus tepat tiga dan belum pernah dibuka.');
                }
                $links = [];
                foreach ($oldHubs->sortBy('supervisor_code') as $hub) {
                    $hub->forceFill(['revoked_at' => now()])->save();
                    $result = $this->generateHub->handle(
                        $actor,
                        $hub->load('project', 'reviewers.round.survey'),
                        $expiresAt,
                    );
                    $links[] = [
                        'supervisor_code' => $hub->supervisor_code,
                        'supervisor_name' => $result->hub->supervisor_name,
                        'expires_at' => $result->hub->expires_at->toISOString(),
                        'url' => $result->url,
                    ];
                }

                $payload = json_encode([
                    'generated_at' => now()->toISOString(),
                    'project_id' => $project->getKey(),
                    'instrument_version' => PharmVrInstrumentV2Catalog::VERSION,
                    'links' => $links,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                if (file_put_contents($outputPath, $payload."\n", LOCK_EX) === false || ! chmod($outputPath, 0600)) {
                    throw new RuntimeException('File private tautan tidak dapat ditulis dengan aman.');
                }

                return [
                    'output_path' => $outputPath,
                    'sha256' => hash_file('sha256', $outputPath),
                    'instruments' => $surveys->count(),
                    'questions' => $questionCounts,
                    'old_hubs_revoked' => $oldHubs->count(),
                    'new_hubs_created' => count($links),
                ];
            });
        } catch (Throwable $exception) {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
            throw $exception;
        }
    }

    private function guardSurvey(Survey $survey): void
    {
        if ($survey->status !== Survey::STATUS_DRAFT
            || $survey->is_public
            || $survey->published_at !== null
            || $survey->responses_count !== 0
            || $survey->questions->count() !== self::EXPECTED_COUNTS[$survey->instrument_code]) {
            throw new RuntimeException('Instrumen tidak aman untuk diperbarui: '.$survey->instrument_code);
        }
    }

    private function guardRound(SurveySupervisorReviewRound $round): void
    {
        if ($round->finalized_at !== null || ! $round->isOpen() || $round->reviewers->count() !== 3) {
            throw new RuntimeException('Ronde review tidak aman untuk diganti snapshot-nya.');
        }

        foreach ($round->reviewers as $reviewer) {
            if ($reviewer->status !== SurveySupervisorReviewer::STATUS_NOT_OPENED
                || $reviewer->opened_at !== null
                || $reviewer->submitted_at !== null
                || $reviewer->comments()->exists()
                || $reviewer->revisions()->exists()) {
                throw new RuntimeException('Reviewer sudah memiliki aktivitas; pembaruan dihentikan.');
            }
        }
    }

    private function guardOutputPath(string $outputPath): void
    {
        $privateDirectory = storage_path('app/private');
        if (! is_dir($privateDirectory) && ! mkdir($privateDirectory, 0700, true) && ! is_dir($privateDirectory)) {
            throw new RuntimeException('Direktori private tidak dapat dibuat.');
        }

        $directory = str_replace('\\', '/', dirname($outputPath));
        $expected = str_replace('\\', '/', $privateDirectory);
        if ($directory !== $expected || is_file($outputPath)) {
            throw new RuntimeException('Output harus berupa file baru di storage/app/private.');
        }
    }
}
