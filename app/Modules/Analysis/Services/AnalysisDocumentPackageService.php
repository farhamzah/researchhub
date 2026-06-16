<?php

namespace App\Modules\Analysis\Services;

use App\Models\AnalysisDocumentPackage;
use App\Models\AnalysisSynthesisItem;
use App\Models\Survey;
use App\Models\SurveyDistributionBatch;
use App\Models\SurveyDistributionRecipient;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Surveys\Services\SurveyDistributionCenterService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AnalysisDocumentPackageService
{
    public function __construct(
        private readonly AddieAnalysisDashboardService $dashboardService,
        private readonly SurveyDistributionCenterService $distributionService,
        private readonly AnalysisCollectionMonitoringService $collectionMonitoringService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Survey $survey, User $user): array
    {
        $survey->load([
            'project',
            'pages.questions',
            'questions',
            'responses',
            'validationRounds.assignments.recommendation',
            'readabilityRounds.participants.response',
            'synthesisItems',
            'distributionBatches.recipients',
        ]);

        $package = $this->package($survey, $user);
        $instruments = $this->instruments($survey);
        $dashboard = $this->dashboardService->build($survey);
        $distribution = $this->distributionService->build($survey, $user);
        $collection = $this->collectionMonitoringService->build($survey, $user);
        $synthesisItems = $this->synthesisItems($survey);
        $missingItems = $this->missingItems($instruments, $dashboard, $collection, $synthesisItems);

        return [
            'package' => $package,
            'survey' => $survey,
            'project' => $survey->project,
            'instruments' => $instruments,
            'instrument_list' => $this->instrumentList($instruments, $survey, $dashboard),
            'validation' => $dashboard['validation'],
            'readability' => $dashboard['readability'],
            'distribution' => $distribution,
            'distribution_rows' => $this->distributionRows($survey),
            'collection' => $collection,
            'synthesis_items' => $synthesisItems,
            'readiness_recommendation' => $this->readinessRecommendation($collection['readiness']['status']),
            'missing_items' => $missingItems,
            'nav' => $this->nav($survey),
            'generated_at' => now(),
        ];
    }

    public function finalize(Survey $survey, User $user): AnalysisDocumentPackage
    {
        $data = $this->build($survey, $user);
        /** @var AnalysisDocumentPackage $package */
        $package = $data['package'];
        $package->update([
            'status' => AnalysisDocumentPackage::STATUS_FINAL,
            'snapshot_json' => $this->snapshot($data),
            'finalized_at' => now(),
        ]);

        return $package->fresh();
    }

    private function package(Survey $survey, User $user): AnalysisDocumentPackage
    {
        return AnalysisDocumentPackage::firstOrCreate([
            'survey_id' => $survey->getKey(),
        ], [
            'project_id' => $survey->project_id,
            'title' => 'Paket Instrumen dan Laporan Tahap Analysis PharmVR',
            'document_code' => 'PHARMVR-ADDIE-ANALYSIS',
            'version' => 'Draft v1',
            'document_date' => today(),
            'researcher_name' => $user->name,
            'institution' => 'Universitas Padjadjaran',
            'study_program' => 'Program Studi Doktor Ilmu Farmasi',
            'stage' => 'ADDIE Analysis',
            'status' => AnalysisDocumentPackage::STATUS_DRAFT,
            'purpose_text' => 'This document compiles the instruments and supporting reports used in the ADDIE Analysis stage of PharmVR development. The package is intended for supervisor/promotor review before entering the Design stage.',
            'created_by' => $user->getKey(),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function instruments(Survey $survey): array
    {
        $groupKey = $survey->analysis_group_key ?: Survey::ANALYSIS_GROUP_PHARMVR_ADDIE;
        $related = Survey::query()
            ->with(['pages.questions', 'questions', 'responses'])
            ->where('project_id', $survey->project_id)
            ->where(function ($query) use ($survey, $groupKey): void {
                $query
                    ->where('id', $survey->getKey())
                    ->orWhere('parent_survey_id', $survey->getKey())
                    ->orWhere('analysis_group_key', $groupKey);
            })
            ->get();

        $student = $related->firstWhere('id', $survey->getKey()) ?: $survey->fresh(['pages.questions', 'questions', 'responses']);
        $lecturer = $related->firstWhere('instrument_type', Survey::INSTRUMENT_ANALYSIS_LECTURER);
        $practitioner = $related->firstWhere('instrument_type', Survey::INSTRUMENT_PRACTITIONER_INTERVIEW);

        return [
            'student' => $this->instrumentData($student, 'Student Questionnaire', 'Mahasiswa farmasi', 'Questionnaire'),
            'lecturer' => $this->instrumentData($lecturer, 'Lecturer Questionnaire', 'Dosen/pengampu terkait CPOB/GMP', 'Questionnaire'),
            'practitioner' => $this->instrumentData($practitioner, 'Practitioner Interview Form', 'Praktisi atau ahli CPOB/GMP', 'Structured Interview / Expert Input Form'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function instrumentData(?Survey $survey, string $label, string $targetRespondent, string $type): array
    {
        if (! $survey) {
            return [
                'exists' => false,
                'label' => $label,
                'target_respondent' => $targetRespondent,
                'type' => $type,
                'survey' => null,
                'sections' => [],
                'question_count' => 0,
                'response_count' => 0,
                'status' => 'Missing',
                'intro_status' => 'Missing',
            ];
        }

        return [
            'exists' => true,
            'label' => $label,
            'target_respondent' => $targetRespondent,
            'type' => $type,
            'survey' => $survey,
            'sections' => $this->sections($survey),
            'question_count' => $survey->questions->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)->count(),
            'response_count' => $survey->responses->where('status', SurveyResponse::STATUS_SUBMITTED)->count(),
            'status' => Str::title(str_replace('_', ' ', $survey->status)),
            'intro_status' => $this->introStatus($survey),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sections(Survey $survey): array
    {
        $pages = $survey->pages->isNotEmpty()
            ? $survey->pages
            : collect([(object) [
                'title' => 'Questions',
                'description' => null,
                'questions' => $survey->questions,
            ]]);

        return $pages
            ->map(fn (mixed $page): array => [
                'title' => $page->title ?: 'Untitled Section',
                'description' => $page->description ?? null,
                'questions' => collect($page->questions)
                    ->reject(fn (SurveyQuestion $question): bool => $question->type === SurveyQuestion::TYPE_HIDDEN)
                    ->sortBy('sort_order')
                    ->values()
                    ->map(fn (SurveyQuestion $question): array => [
                        'label' => $question->label,
                        'type' => Str::title(str_replace('_', ' ', $question->type)),
                        'required' => $question->is_required,
                        'help_text' => $question->help_text,
                        'options' => $this->optionLines($question),
                    ])
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function optionLines(SurveyQuestion $question): array
    {
        $options = $question->options ?? [];
        $settings = $question->settings ?? [];

        if (isset($options['choices']) && is_array($options['choices'])) {
            return array_map('strval', $options['choices']);
        }

        if (isset($options['scale']) && is_array($options['scale'])) {
            return array_map('strval', $options['scale']);
        }

        if (isset($settings['scale']) && is_array($settings['scale'])) {
            return array_map('strval', $settings['scale']);
        }

        if (isset($options['rows']) || isset($options['columns'])) {
            return [
                'Rows: '.implode(', ', array_map('strval', $options['rows'] ?? [])),
                'Columns: '.implode(', ', array_map('strval', $options['columns'] ?? [])),
            ];
        }

        if (array_is_list($options)) {
            return array_map('strval', $options);
        }

        return [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $instruments
     * @return array<int, array<string, mixed>>
     */
    private function instrumentList(array $instruments, Survey $survey, array $dashboard): array
    {
        $validation = $dashboard['validation'];
        $readability = $dashboard['readability'];

        return [
            $this->instrumentListRow($instruments['student']),
            $this->instrumentListRow($instruments['lecturer']),
            $this->instrumentListRow($instruments['practitioner']),
            [
                'name' => 'Expert Validation Form',
                'type' => 'Expert validation',
                'target_respondent' => 'Validator ahli',
                'section_count' => $survey->validationRounds->count(),
                'question_count' => $survey->questions->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)->count(),
                'intro_status' => 'Instructions available',
                'response_count' => $validation['submitted_count'],
                'status' => $validation['submitted_count'] > 0 ? 'Submitted' : 'Pending',
            ],
            [
                'name' => 'Readability Test Form',
                'type' => 'Readability review',
                'target_respondent' => 'Pilot participant',
                'section_count' => $survey->readabilityRounds->count(),
                'question_count' => $survey->questions->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)->count(),
                'intro_status' => 'Instructions available',
                'response_count' => $readability['submitted_count'],
                'status' => $readability['submitted_count'] > 0 ? 'Submitted' : 'Pending',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $instrument
     * @return array<string, mixed>
     */
    private function instrumentListRow(array $instrument): array
    {
        return [
            'name' => $instrument['survey']?->title ?? $instrument['label'],
            'type' => $instrument['type'],
            'target_respondent' => $instrument['target_respondent'],
            'section_count' => count($instrument['sections']),
            'question_count' => $instrument['question_count'],
            'intro_status' => $instrument['intro_status'],
            'response_count' => $instrument['response_count'],
            'status' => $instrument['status'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function distributionRows(Survey $survey): array
    {
        return $survey->distributionBatches
            ->map(function (SurveyDistributionBatch $batch): array {
                $recipients = $batch->recipients;

                return [
                    'audience' => SurveyDistributionBatch::AUDIENCE_LABELS[$batch->audience_type] ?? $batch->audience_type,
                    'link_ready_count' => $recipients->where('status', SurveyDistributionRecipient::STATUS_LINK_READY)->count(),
                    'sent_manually_count' => $recipients->where('status', SurveyDistributionRecipient::STATUS_SENT_MANUALLY)->count(),
                    'pending_count' => $recipients->whereIn('status', [
                        SurveyDistributionRecipient::STATUS_DRAFT,
                        SurveyDistributionRecipient::STATUS_LINK_READY,
                        SurveyDistributionRecipient::STATUS_SENT_MANUALLY,
                        SurveyDistributionRecipient::STATUS_OPENED,
                    ])->count(),
                    'submitted_count' => $recipients->where('status', SurveyDistributionRecipient::STATUS_SUBMITTED)->count(),
                    'revoked_count' => $recipients->where('status', SurveyDistributionRecipient::STATUS_REVOKED)->count(),
                    'deadline' => $batch->deadline,
                    'notes' => $batch->notes,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, AnalysisSynthesisItem>
     */
    private function synthesisItems(Survey $survey): Collection
    {
        return $survey->synthesisItems
            ->sortByDesc(fn (AnalysisSynthesisItem $item): string => sprintf(
                '%d-%d-%s',
                in_array($item->priority_level, [AnalysisSynthesisItem::PRIORITY_CRITICAL, AnalysisSynthesisItem::PRIORITY_HIGH], true) ? 1 : 0,
                in_array($item->status, [AnalysisSynthesisItem::STATUS_ACCEPTED, AnalysisSynthesisItem::STATUS_IN_DESIGN], true) ? 1 : 0,
                $item->created_at?->timestamp ?? 0,
            ))
            ->values();
    }

    /**
     * @param  array<string, array<string, mixed>>  $instruments
     * @param  Collection<int, AnalysisSynthesisItem>  $synthesisItems
     * @return array<int, string>
     */
    private function missingItems(array $instruments, array $dashboard, array $collection, Collection $synthesisItems): array
    {
        $items = [];

        if (! $instruments['lecturer']['exists']) {
            $items[] = 'Lecturer questionnaire missing.';
        }

        if (! $instruments['practitioner']['exists']) {
            $items[] = 'Practitioner interview form missing.';
        }

        if (($dashboard['validation']['submitted_count'] ?? 0) <= 0) {
            $items[] = 'Expert validation has no submitted validator yet.';
        }

        if (($dashboard['readability']['submitted_count'] ?? 0) <= 0) {
            $items[] = 'Readability test has no submitted participant yet.';
        }

        foreach ($collection['sources'] as $source) {
            if (! $source['is_minimum_met']) {
                $items[] = $source['label'].' below minimum target.';
            }
        }

        if ($synthesisItems->where('status', AnalysisSynthesisItem::STATUS_ACCEPTED)->isEmpty()) {
            $items[] = 'No accepted synthesis item yet.';
        }

        return array_values(array_unique($items));
    }

    private function readinessRecommendation(string $status): string
    {
        return match ($status) {
            'Fully Ready' => 'Target pengumpulan data tahap Analysis telah terpenuhi. Dokumen dapat digunakan sebagai dasar finalisasi tahap Analysis dan transisi ke tahap Design.',
            'Minimum Ready' => 'Data minimum tahap Analysis telah terpenuhi. Peneliti dapat mulai menyusun sintesis final dan menyiapkan tahap Design.',
            'Partially Ready' => 'Sebagian sumber data telah memenuhi minimum, namun masih diperlukan tindak lanjut pada sumber yang belum memenuhi target.',
            default => 'Data Analysis belum memadai untuk difinalisasi. Pengumpulan data perlu dilanjutkan.',
        };
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
            'Analysis Package' => route('admin.surveys.analysis-package.index', ['survey' => $survey]),
            'Back to Surveys' => route('filament.admin.resources.surveys.index'),
        ];
    }

    private function introStatus(Survey $survey): string
    {
        return filled($survey->intro_text)
            ? ($survey->require_consent_before_start ? 'Intro + consent ready' : 'Intro ready')
            : 'Intro missing';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function snapshot(array $data): array
    {
        return [
            'instrument_list' => $data['instrument_list'],
            'validation_summary' => [
                'assigned_count' => $data['validation']['assigned_count'],
                'submitted_count' => $data['validation']['submitted_count'],
                'average_score' => $data['validation']['average_score'],
                'percentage' => $data['validation']['percentage'],
                'category' => $data['validation']['category'],
            ],
            'readability_summary' => [
                'participant_count' => $data['readability']['participant_count'],
                'submitted_count' => $data['readability']['submitted_count'],
                'average_score' => $data['readability']['average_score'],
                'category' => $data['readability']['category'],
            ],
            'distribution_summary' => $data['distribution_rows'],
            'collection_monitoring_summary' => collect($data['collection']['sources'])
                ->map(fn (array $source): array => [
                    'source_type' => $source['source_type'],
                    'label' => $source['label'],
                    'current_count' => $source['current_count'],
                    'minimum_count' => $source['minimum_count'],
                    'target_count' => $source['target_count'],
                    'status' => $source['status_label'],
                ])
                ->all(),
            'synthesis_summary' => $data['synthesis_items']
                ->map(fn (AnalysisSynthesisItem $item): array => [
                    'source_type' => $item->source_type,
                    'theme' => $item->theme,
                    'finding' => $item->finding,
                    'priority_level' => $item->priority_level,
                    'status' => $item->status,
                ])
                ->all(),
            'readiness_status' => $data['collection']['readiness']['status'],
            'generated_at' => now()->toISOString(),
        ];
    }
}
