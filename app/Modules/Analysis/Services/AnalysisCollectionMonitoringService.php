<?php

namespace App\Modules\Analysis\Services;

use App\Models\AnalysisCollectionTarget;
use App\Models\AnalysisSynthesisItem;
use App\Models\Survey;
use App\Models\SurveyDistributionBatch;
use App\Models\SurveyDistributionRecipient;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyResponse;
use App\Models\SurveyValidationAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

class AnalysisCollectionMonitoringService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Survey $survey, User $user): array
    {
        $survey->load([
            'project',
            'responses',
            'synthesisItems',
            'validationRounds.assignments',
            'readabilityRounds.participants.response',
            'distributionBatches.recipients',
            'analysisCollectionTargets',
        ]);

        $instruments = $this->analysisInstruments($survey);
        $targets = $this->targets($survey, $user, $instruments);
        $batches = $survey->distributionBatches->keyBy('audience_type');
        $sources = $this->sourceRows($survey, $instruments, $targets, $batches);
        $readiness = $this->readiness($sources);

        return [
            'sources' => $sources,
            'readiness' => $readiness,
            'follow_up' => $this->followUp($sources),
            'summary_cards' => $this->summaryCards($sources, $readiness),
            'nav' => $this->nav($survey),
            'status_labels' => AnalysisCollectionTarget::STATUS_LABELS,
            'generated_at' => now(),
        ];
    }

    /**
     * @return array<string, Survey|null>
     */
    private function analysisInstruments(Survey $survey): array
    {
        $groupKey = $survey->analysis_group_key ?: Survey::ANALYSIS_GROUP_PHARMVR_ADDIE;
        $related = Survey::query()
            ->with([
                'responses',
            ])
            ->where('project_id', $survey->project_id)
            ->where(function ($query) use ($survey, $groupKey): void {
                $query
                    ->where('id', $survey->getKey())
                    ->orWhere('parent_survey_id', $survey->getKey())
                    ->orWhere('analysis_group_key', $groupKey);
            })
            ->get();

        return [
            AnalysisCollectionTarget::SOURCE_STUDENT_QUESTIONNAIRE => $related->firstWhere('id', $survey->getKey()) ?: $survey,
            AnalysisCollectionTarget::SOURCE_LECTURER_QUESTIONNAIRE => $related->firstWhere('instrument_type', Survey::INSTRUMENT_ANALYSIS_LECTURER),
            AnalysisCollectionTarget::SOURCE_PRACTITIONER_INTERVIEW => $related->firstWhere('instrument_type', Survey::INSTRUMENT_PRACTITIONER_INTERVIEW),
        ];
    }

    /**
     * @param  array<string, Survey|null>  $instruments
     * @return Collection<string, AnalysisCollectionTarget>
     */
    private function targets(Survey $survey, User $user, array $instruments): Collection
    {
        $targets = collect(AnalysisCollectionTarget::SOURCES)
            ->mapWithKeys(function (string $sourceType) use ($survey, $user, $instruments): array {
                $target = AnalysisCollectionTarget::firstOrCreate([
                    'survey_id' => $survey->getKey(),
                    'source_type' => $sourceType,
                ], [
                    'project_id' => $survey->project_id,
                    'target_survey_id' => ($instruments[$sourceType] ?? null)?->getKey(),
                    'label' => AnalysisCollectionTarget::SOURCE_LABELS[$sourceType],
                    'minimum_count' => AnalysisCollectionTarget::DEFAULTS[$sourceType]['minimum'],
                    'target_count' => AnalysisCollectionTarget::DEFAULTS[$sourceType]['target'],
                    'created_by' => $user->getKey(),
                ]);

                $targetSurveyId = ($instruments[$sourceType] ?? null)?->getKey();
                if ($target->target_survey_id !== $targetSurveyId || $target->project_id !== $survey->project_id) {
                    $target->forceFill([
                        'project_id' => $survey->project_id,
                        'target_survey_id' => $targetSurveyId,
                    ])->save();
                }

                return [$sourceType => $target->fresh()];
            });

        return $targets;
    }

    /**
     * @param  array<string, Survey|null>  $instruments
     * @param  Collection<string, AnalysisCollectionTarget>  $targets
     * @param  Collection<string, SurveyDistributionBatch>  $batches
     * @return array<int, array<string, mixed>>
     */
    private function sourceRows(Survey $survey, array $instruments, Collection $targets, Collection $batches): array
    {
        $validationAssignments = $survey->validationRounds->flatMap->assignments;
        $readabilityParticipants = $survey->readabilityRounds->flatMap->participants;
        $acceptedItems = $survey->synthesisItems->where('status', AnalysisSynthesisItem::STATUS_ACCEPTED);
        $designReadyItems = $survey->synthesisItems->filter(fn (AnalysisSynthesisItem $item): bool => in_array($item->status, [
            AnalysisSynthesisItem::STATUS_ACCEPTED,
            AnalysisSynthesisItem::STATUS_IN_DESIGN,
            AnalysisSynthesisItem::STATUS_IN_DEVELOPMENT,
        ], true));

        $configs = [
            AnalysisCollectionTarget::SOURCE_STUDENT_QUESTIONNAIRE => [
                'instrument' => $instruments[AnalysisCollectionTarget::SOURCE_STUDENT_QUESTIONNAIRE],
                'audience' => SurveyDistributionBatch::AUDIENCE_STUDENT,
                'current' => $this->submittedResponses($instruments[AnalysisCollectionTarget::SOURCE_STUDENT_QUESTIONNAIRE]),
                'assigned' => $instruments[AnalysisCollectionTarget::SOURCE_STUDENT_QUESTIONNAIRE]?->responses->count() ?? 0,
                'metric_label' => 'Submitted responses',
                'link_route' => $instruments[AnalysisCollectionTarget::SOURCE_STUDENT_QUESTIONNAIRE]
                    ? route('admin.surveys.responses.index', ['survey' => $instruments[AnalysisCollectionTarget::SOURCE_STUDENT_QUESTIONNAIRE]])
                    : null,
            ],
            AnalysisCollectionTarget::SOURCE_LECTURER_QUESTIONNAIRE => [
                'instrument' => $instruments[AnalysisCollectionTarget::SOURCE_LECTURER_QUESTIONNAIRE],
                'audience' => SurveyDistributionBatch::AUDIENCE_LECTURER,
                'current' => $this->submittedResponses($instruments[AnalysisCollectionTarget::SOURCE_LECTURER_QUESTIONNAIRE]),
                'assigned' => $instruments[AnalysisCollectionTarget::SOURCE_LECTURER_QUESTIONNAIRE]?->responses->count() ?? 0,
                'metric_label' => 'Submitted responses',
                'link_route' => $instruments[AnalysisCollectionTarget::SOURCE_LECTURER_QUESTIONNAIRE]
                    ? route('admin.surveys.responses.index', ['survey' => $instruments[AnalysisCollectionTarget::SOURCE_LECTURER_QUESTIONNAIRE]])
                    : null,
            ],
            AnalysisCollectionTarget::SOURCE_PRACTITIONER_INTERVIEW => [
                'instrument' => $instruments[AnalysisCollectionTarget::SOURCE_PRACTITIONER_INTERVIEW],
                'audience' => SurveyDistributionBatch::AUDIENCE_PRACTITIONER,
                'current' => $this->submittedResponses($instruments[AnalysisCollectionTarget::SOURCE_PRACTITIONER_INTERVIEW]),
                'assigned' => $instruments[AnalysisCollectionTarget::SOURCE_PRACTITIONER_INTERVIEW]?->responses->count() ?? 0,
                'metric_label' => 'Submitted responses',
                'link_route' => $instruments[AnalysisCollectionTarget::SOURCE_PRACTITIONER_INTERVIEW]
                    ? route('admin.surveys.responses.index', ['survey' => $instruments[AnalysisCollectionTarget::SOURCE_PRACTITIONER_INTERVIEW]])
                    : null,
            ],
            AnalysisCollectionTarget::SOURCE_EXPERT_VALIDATION => [
                'instrument' => null,
                'audience' => SurveyDistributionBatch::AUDIENCE_EXPERT_VALIDATOR,
                'current' => $validationAssignments->filter(fn (SurveyValidationAssignment $assignment): bool => $assignment->isSubmitted())->count(),
                'assigned' => $validationAssignments->count(),
                'metric_label' => 'Submitted validators',
                'link_route' => route('admin.surveys.validation.index', ['survey' => $survey]),
            ],
            AnalysisCollectionTarget::SOURCE_READABILITY_TEST => [
                'instrument' => null,
                'audience' => SurveyDistributionBatch::AUDIENCE_READABILITY_PARTICIPANT,
                'current' => $readabilityParticipants->filter(fn (SurveyReadabilityParticipant $participant): bool => $participant->isSubmitted())->count(),
                'assigned' => $readabilityParticipants->count(),
                'metric_label' => 'Submitted participants',
                'link_route' => route('admin.surveys.readability.index', ['survey' => $survey]),
            ],
            AnalysisCollectionTarget::SOURCE_SYNTHESIS_MATRIX => [
                'instrument' => null,
                'audience' => null,
                'current' => $acceptedItems->count(),
                'assigned' => $survey->synthesisItems->count(),
                'metric_label' => 'Accepted items',
                'link_route' => route('admin.surveys.analysis.index', ['survey' => $survey]),
                'extra' => [
                    'proposed_items' => $survey->synthesisItems->count(),
                    'accepted_items' => $acceptedItems->count(),
                    'high_priority_items' => $survey->synthesisItems
                        ->whereIn('priority_level', [AnalysisSynthesisItem::PRIORITY_HIGH, AnalysisSynthesisItem::PRIORITY_CRITICAL])
                        ->count(),
                    'design_ready_items' => $designReadyItems->count(),
                ],
            ],
        ];

        return collect(AnalysisCollectionTarget::SOURCES)
            ->map(function (string $sourceType) use ($targets, $batches, $configs): array {
                $target = $targets->get($sourceType);
                $config = $configs[$sourceType];
                $batch = $config['audience'] ? $batches->get($config['audience']) : null;
                $isApplicable = ! in_array($sourceType, [
                    AnalysisCollectionTarget::SOURCE_LECTURER_QUESTIONNAIRE,
                    AnalysisCollectionTarget::SOURCE_PRACTITIONER_INTERVIEW,
                ], true) || $config['instrument'] instanceof Survey;
                $status = $this->status($target, (int) $config['current'], $isApplicable);

                return [
                    'source_type' => $sourceType,
                    'label' => $target->label,
                    'target' => $target,
                    'instrument' => $config['instrument'],
                    'current_count' => (int) $config['current'],
                    'assigned_count' => (int) $config['assigned'],
                    'minimum_count' => $target->minimum_count,
                    'target_count' => $target->target_count,
                    'minimum_percent' => $this->percent((int) $config['current'], $target->minimum_count),
                    'target_percent' => $this->percent((int) $config['current'], $target->target_count),
                    'completion_rate' => $this->percent((int) $config['current'], max(1, $target->target_count)),
                    'status' => $status,
                    'status_label' => AnalysisCollectionTarget::STATUS_LABELS[$status],
                    'is_minimum_met' => $isApplicable && (int) $config['current'] >= $target->minimum_count && $target->minimum_count > 0,
                    'is_target_met' => $isApplicable && (int) $config['current'] >= $target->target_count && $target->target_count > 0,
                    'is_applicable' => $isApplicable,
                    'metric_label' => $config['metric_label'],
                    'link_route' => $config['link_route'],
                    'public_route' => $config['instrument'] instanceof Survey && $config['instrument']->canReceiveResponses()
                        ? route('survey.show', ['survey' => $config['instrument']->slug])
                        : null,
                    'distribution' => $this->distributionSummary($batch),
                    'extra' => $config['extra'] ?? [],
                    'suggested_action' => $this->suggestedAction($sourceType, $status, $batch, (int) $config['current']),
                ];
            })
            ->values()
            ->all();
    }

    private function submittedResponses(?Survey $survey): int
    {
        if (! $survey) {
            return 0;
        }

        return $survey->responses
            ->where('status', SurveyResponse::STATUS_SUBMITTED)
            ->count();
    }

    private function status(AnalysisCollectionTarget $target, int $currentCount, bool $isApplicable): string
    {
        if (! $isApplicable) {
            return AnalysisCollectionTarget::STATUS_NOT_APPLICABLE;
        }

        if ($target->due_date && $target->due_date->isPast() && $currentCount < $target->target_count) {
            return AnalysisCollectionTarget::STATUS_OVERDUE;
        }

        if ($target->target_count > 0 && $currentCount >= $target->target_count) {
            return AnalysisCollectionTarget::STATUS_TARGET_MET;
        }

        if ($target->minimum_count > 0 && $currentCount >= $target->minimum_count) {
            return AnalysisCollectionTarget::STATUS_MINIMUM_MET;
        }

        if ($currentCount > 0) {
            return AnalysisCollectionTarget::STATUS_COLLECTING;
        }

        return AnalysisCollectionTarget::STATUS_NOT_STARTED;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function distributionSummary(?SurveyDistributionBatch $batch): ?array
    {
        if (! $batch) {
            return null;
        }

        $recipients = $batch->recipients;

        return [
            'title' => $batch->title,
            'deadline' => $batch->deadline,
            'status' => $batch->status,
            'sent_manually_count' => $recipients->where('status', SurveyDistributionRecipient::STATUS_SENT_MANUALLY)->count(),
            'link_ready_count' => $recipients->where('status', SurveyDistributionRecipient::STATUS_LINK_READY)->count(),
            'submitted_count' => $recipients->where('status', SurveyDistributionRecipient::STATUS_SUBMITTED)->count(),
            'pending_count' => $recipients->whereIn('status', [
                SurveyDistributionRecipient::STATUS_DRAFT,
                SurveyDistributionRecipient::STATUS_LINK_READY,
                SurveyDistributionRecipient::STATUS_SENT_MANUALLY,
                SurveyDistributionRecipient::STATUS_OPENED,
            ])->count(),
            'revoked_count' => $recipients->where('status', SurveyDistributionRecipient::STATUS_REVOKED)->count(),
            'is_overdue' => $batch->deadline?->isPast() ?? false,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @return array<string, mixed>
     */
    private function readiness(array $sources): array
    {
        $required = collect($sources);
        $minimumMet = $required->where('is_minimum_met', true)->count();
        $targetMet = $required->where('is_target_met', true)->count();
        $requiredCount = $required->count();

        $status = match (true) {
            $targetMet === $requiredCount => 'Fully Ready',
            $minimumMet === $requiredCount => 'Minimum Ready',
            $minimumMet > 0 => 'Partially Ready',
            default => 'Not Ready',
        };

        return [
            'status' => $status,
            'minimum_met_count' => $minimumMet,
            'target_met_count' => $targetMet,
            'required_count' => $requiredCount,
            'recommendation' => match ($status) {
                'Fully Ready' => 'Target pengumpulan data telah terpenuhi. Analysis siap difinalisasi dan dilaporkan.',
                'Minimum Ready' => 'Data minimal telah terpenuhi. Peneliti dapat mulai menyusun sintesis awal dan menyiapkan tahap Design.',
                'Partially Ready' => 'Sebagian data sudah terkumpul, tetapi masih ada sumber data yang belum memenuhi minimum.',
                default => 'Data Analysis belum cukup. Lanjutkan distribusi dan follow-up responden.',
            },
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @return array<int, array<string, mixed>>
     */
    private function followUp(array $sources): array
    {
        return collect($sources)
            ->filter(fn (array $source): bool => $source['status'] === AnalysisCollectionTarget::STATUS_OVERDUE
                || ! $source['is_minimum_met']
                || (($source['distribution']['pending_count'] ?? 0) > 0)
                || (($source['distribution']['sent_manually_count'] ?? 0) + ($source['distribution']['link_ready_count'] ?? 0) > 0 && $source['current_count'] === 0))
            ->map(fn (array $source): array => [
                'source' => $source['label'],
                'current_count' => $source['current_count'],
                'minimum_count' => $source['minimum_count'],
                'target_count' => $source['target_count'],
                'due_date' => $source['target']->due_date,
                'status_label' => $source['status_label'],
                'suggested_action' => $source['suggested_action'],
                'route' => $source['link_route'],
            ])
            ->values()
            ->all();
    }

    private function suggestedAction(string $sourceType, string $status, ?SurveyDistributionBatch $batch, int $currentCount): string
    {
        if ($status === AnalysisCollectionTarget::STATUS_NOT_APPLICABLE) {
            return in_array($sourceType, [
                AnalysisCollectionTarget::SOURCE_LECTURER_QUESTIONNAIRE,
                AnalysisCollectionTarget::SOURCE_PRACTITIONER_INTERVIEW,
            ], true)
                ? 'Buat instrumen terkait dari Analysis Dashboard, lalu publish dan distribusikan link.'
                : 'Tambahkan konfigurasi sumber data sebelum follow-up.';
        }

        if ($status === AnalysisCollectionTarget::STATUS_OVERDUE) {
            return 'Perpanjang deadline atau kirim ulang pesan WhatsApp/email dari Distribution Center.';
        }

        if ($batch && $currentCount === 0) {
            return 'Hubungi responden yang belum submit atau regenerate link jika link lama bermasalah.';
        }

        return match ($sourceType) {
            AnalysisCollectionTarget::SOURCE_EXPERT_VALIDATION => 'Tambahkan validator atau kirim ulang undangan validasi ahli.',
            AnalysisCollectionTarget::SOURCE_READABILITY_TEST => 'Tambahkan participant uji keterbacaan atau follow-up peserta pending.',
            AnalysisCollectionTarget::SOURCE_SYNTHESIS_MATRIX => 'Terima item sintesis yang sudah kuat atau generate draft synthesis dari Analysis Dashboard.',
            default => 'Kirim ulang pesan WhatsApp/email dari Distribution Center dan pantau submission.',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @return array<int, array<string, string>>
     */
    private function summaryCards(array $sources, array $readiness): array
    {
        $collection = collect($sources);

        return [
            ['label' => 'Readiness', 'value' => $readiness['status']],
            ['label' => 'Minimum Met', 'value' => $readiness['minimum_met_count'].' / '.$readiness['required_count']],
            ['label' => 'Target Met', 'value' => $readiness['target_met_count'].' / '.$readiness['required_count']],
            ['label' => 'Need Follow-up', 'value' => (string) $collection->filter(fn (array $source): bool => ! $source['is_minimum_met'])->count()],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function nav(Survey $survey): array
    {
        return [
            'Builder' => route('admin.surveys.builder.index', ['survey' => $survey]),
            'Validation' => route('admin.surveys.validation.index', ['survey' => $survey]),
            'Readability' => route('admin.surveys.readability.index', ['survey' => $survey]),
            'Analysis Dashboard' => route('admin.surveys.analysis.index', ['survey' => $survey]),
            'Distribution Center' => route('admin.surveys.distribution.index', ['survey' => $survey]),
            'Collection Monitoring' => route('admin.surveys.collection-monitoring.index', ['survey' => $survey]),
            'Back to Surveys' => route('filament.admin.resources.surveys.index'),
        ];
    }

    private function percent(int $value, int $target): int
    {
        if ($target <= 0) {
            return $value > 0 ? 100 : 0;
        }

        return min(100, (int) round(($value / $target) * 100));
    }
}
