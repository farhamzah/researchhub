<?php

namespace App\Modules\Analysis\Services;

use App\Models\AnalysisSynthesisItem;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Modules\Surveys\Services\SurveyReadabilityResultService;
use App\Modules\Validation\Services\SurveyValidationResultService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AddieAnalysisDashboardService
{
    public function __construct(
        private readonly QuestionDescriptiveAnalyzer $questionAnalyzer,
        private readonly SurveyValidationResultService $validationResultService,
        private readonly SurveyReadabilityResultService $readabilityResultService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Survey $survey): array
    {
        $survey->load([
            'project',
            'questions.answers.response',
            'responses.answers',
            'validationRounds.assignments.scores.question',
            'validationRounds.assignments.recommendation',
            'validationRevisions.sourceAssignment.validator',
            'readabilityRounds.participants.response.questionFeedback.question',
            'readabilityRevisions.question',
            'synthesisItems' => fn ($query) => $query->latest(),
        ]);

        $submittedResponses = $survey->responses
            ->filter(fn (SurveyResponse $response): bool => $response->status === SurveyResponse::STATUS_SUBMITTED
                && ! $response->is_test_response
                && ! $response->excluded_from_analysis)
            ->values();
        $responseSummary = $this->responseSummary($survey, $submittedResponses);
        $priority = $this->prioritySummary($responseSummary);
        $validation = $this->validationSummary($survey);
        $readability = $this->readabilitySummary($survey);
        $readiness = $this->readiness($submittedResponses, $validation, $readability, $survey->synthesisItems);

        return [
            'summary_cards' => [
                ['label' => 'Total Questions', 'value' => (string) $survey->questions->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)->count()],
                ['label' => 'Main Survey Responses', 'value' => (string) $submittedResponses->count()],
                ['label' => 'Expert Validators', 'value' => (string) $validation['assigned_count']],
                ['label' => 'Expert Validation Average', 'value' => $this->format($validation['average_score'])],
                ['label' => 'Readability Participants', 'value' => (string) $readability['participant_count']],
                ['label' => 'Readability Average', 'value' => $this->format($readability['average_score'])],
                ['label' => 'Synthesis Items', 'value' => (string) $survey->synthesisItems->count()],
                ['label' => 'Ready for Design', 'value' => $readiness['short_label']],
            ],
            'response_summary' => $responseSummary,
            'priority' => $priority,
            'validation' => $validation,
            'readability' => $readability,
            'readiness' => $readiness,
            'synthesis_items' => $survey->synthesisItems,
            'analysis_instruments' => $this->analysisInstruments($survey),
            'filters' => $this->filters($survey->synthesisItems),
        ];
    }

    /**
     * @return array{created: int, skipped: int}
     */
    public function generateDraftSynthesis(Survey $survey): array
    {
        $dashboard = $this->build($survey);
        $drafts = collect()
            ->merge($this->draftsFromPriority($survey, $dashboard['priority']))
            ->merge($this->draftsFromResponses($survey, $dashboard['response_summary']))
            ->merge($this->draftsFromRelatedInstruments($survey))
            ->merge($this->draftsFromValidation($survey, $dashboard['validation']))
            ->merge($this->draftsFromReadability($survey, $dashboard['readability']));

        $created = 0;
        $skipped = 0;

        foreach ($drafts as $draft) {
            $item = AnalysisSynthesisItem::firstOrCreate([
                'survey_id' => $survey->getKey(),
                'source_type' => $draft['source_type'],
                'theme' => $draft['theme'],
                'finding' => $draft['finding'],
            ], [
                'project_id' => $survey->project_id,
                'source_label' => $draft['source_label'] ?? null,
                'evidence_summary' => $draft['evidence_summary'] ?? null,
                'evidence_metric' => $draft['evidence_metric'] ?? null,
                'priority_level' => $draft['priority_level'] ?? AnalysisSynthesisItem::PRIORITY_MEDIUM,
                'design_implication' => $draft['design_implication'] ?? null,
                'development_decision' => $draft['development_decision'] ?? null,
                'mapped_module' => $draft['mapped_module'] ?? null,
                'status' => AnalysisSynthesisItem::STATUS_PROPOSED,
                'researcher_note' => $draft['researcher_note'] ?? 'Generated deterministically from available ADDIE Analysis data.',
            ]);

            $item->wasRecentlyCreated ? $created++ : $skipped++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @param  Collection<int, SurveyResponse>  $submittedResponses
     * @return array<int, array<string, mixed>>
     */
    private function responseSummary(Survey $survey, Collection $submittedResponses): array
    {
        $submittedResponseIds = $submittedResponses->pluck('id')->all();

        return $survey->questions
            ->reject(fn (SurveyQuestion $question): bool => $question->type === SurveyQuestion::TYPE_HIDDEN)
            ->sortBy('sort_order')
            ->values()
            ->map(function (SurveyQuestion $question, int $index) use ($submittedResponseIds, $submittedResponses): array {
                $answers = $question->answers
                    ->filter(fn (SurveyAnswer $answer): bool => in_array($answer->survey_response_id, $submittedResponseIds, true))
                    ->values();
                $analysis = $this->questionAnalyzer->analyze($question, $answers, $submittedResponses->count());

                return [
                    'number' => $index + 1,
                    'question' => $question,
                    'label' => $question->label,
                    'type' => $question->type,
                    'answered_count' => $analysis['answered_count'] ?? 0,
                    'summary_text' => $this->questionSummaryText($analysis),
                    'analysis' => $analysis,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function analysisInstruments(Survey $survey): array
    {
        $related = Survey::query()
            ->withCount([
                'responses' => fn ($query) => $query->official(),
                'responses as submitted_responses_count' => fn ($query) => $query->submitted()->official(),
            ])
            ->where('project_id', $survey->project_id)
            ->where(function ($query) use ($survey): void {
                $query
                    ->where('id', $survey->getKey())
                    ->orWhere('parent_survey_id', $survey->getKey());
            })
            ->get()
            ->keyBy('instrument_type');

        return [
            'student' => $this->instrumentCard(
                $survey->fresh(['project'])->loadCount([
                    'responses' => fn ($query) => $query->official(),
                    'responses as submitted_responses_count' => fn ($query) => $query->submitted()->official(),
                ]),
                'Student Questionnaire',
                'Instrumen utama analisis kebutuhan mahasiswa.',
                false,
                null,
            ),
            'lecturer' => $this->instrumentCard(
                $related->get(Survey::INSTRUMENT_ANALYSIS_LECTURER),
                'Lecturer Questionnaire',
                'Kuesioner analisis kebutuhan dosen PharmVR.',
                true,
                'admin.surveys.analysis.create-lecturer-questionnaire',
            ),
            'practitioner' => $this->instrumentCard(
                $related->get(Survey::INSTRUMENT_PRACTITIONER_INTERVIEW),
                'Practitioner Interview Form',
                'Pedoman wawancara praktisi atau ahli CPOB.',
                true,
                'admin.surveys.analysis.create-practitioner-interview',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function instrumentCard(?Survey $instrument, string $label, string $description, bool $canCreate, ?string $createRoute): array
    {
        return [
            'label' => $label,
            'description' => $description,
            'exists' => $instrument instanceof Survey,
            'survey' => $instrument,
            'response_count' => $instrument?->responses_count ?? 0,
            'submitted_response_count' => $instrument?->submitted_responses_count ?? 0,
            'is_public' => (bool) ($instrument?->is_public ?? false),
            'can_receive_responses' => $instrument?->canReceiveResponses() ?? false,
            'can_create' => $canCreate,
            'create_route' => $createRoute,
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function questionSummaryText(array $analysis): string
    {
        if (($analysis['answered_count'] ?? 0) === 0) {
            return 'Belum ada jawaban terkirim untuk pertanyaan ini.';
        }

        if (isset($analysis['mean'])) {
            return 'Mean '.$this->format($analysis['mean']).', min '.$this->format($analysis['min']).', max '.$this->format($analysis['max']).'.';
        }

        if (isset($analysis['frequencies'])) {
            $top = collect($analysis['frequencies'])->sortByDesc('count')->first();

            return $top
                ? sprintf('Opsi teratas: %s (%d responden, %.2f%%).', $top['value'], $top['count'], $top['percentage'])
                : 'Belum ada frekuensi pilihan.';
        }

        if (isset($analysis['sample_answers'])) {
            return 'Contoh komentar: '.implode(' | ', array_slice($analysis['sample_answers'], 0, 3));
        }

        if (isset($analysis['matrix_summary'])) {
            return 'Ringkasan matrix tersedia; lihat detail distribusi sederhana.';
        }

        return 'Ringkasan tersedia.';
    }

    /**
     * @param  array<int, array<string, mixed>>  $responseSummary
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function prioritySummary(array $responseSummary): array
    {
        $featureRows = [];
        $sceneRows = [];
        $difficultyRows = [];
        $technologyRows = [];

        foreach ($responseSummary as $row) {
            $label = Str::lower($row['label']);
            $analysis = $row['analysis'];

            if ($this->matches($label, ['fitur', 'feature'])) {
                $featureRows = array_merge($featureRows, $this->frequencyRows($row));
            }

            if ($this->matches($label, ['scene', 'skenario', 'ruang', 'vr', 'cpob', 'gmp'])) {
                $sceneRows = array_merge($sceneRows, $this->frequencyRows($row));
            }

            if ($this->matches($label, ['sulit', 'kesulitan', 'difficulty', 'cpob', 'gmp'])) {
                $difficultyRows[] = [
                    'label' => $row['label'],
                    'metric' => isset($analysis['mean']) ? 'Mean '.$this->format($analysis['mean']) : (string) $row['answered_count'].' responses',
                    'summary' => $row['summary_text'],
                ];
            }

            if ($this->matches($label, ['teknologi', 'perangkat', 'readiness', 'vr'])) {
                $technologyRows[] = [
                    'label' => $row['label'],
                    'metric' => isset($analysis['mean']) ? 'Mean '.$this->format($analysis['mean']) : (string) $row['answered_count'].' responses',
                    'summary' => $row['summary_text'],
                ];
            }
        }

        return [
            'features' => collect($featureRows)->sortByDesc('count')->values()->take(12)->all(),
            'scenes' => collect($sceneRows)->sortByDesc('count')->values()->take(12)->all(),
            'difficulties' => collect($difficultyRows)->sortByDesc('metric')->values()->take(8)->all(),
            'technology' => collect($technologyRows)->values()->take(8)->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array<string, mixed>>
     */
    private function frequencyRows(array $row): array
    {
        return collect($row['analysis']['frequencies'] ?? [])
            ->filter(fn (array $frequency): bool => (int) $frequency['count'] > 0)
            ->map(fn (array $frequency): array => [
                'label' => $frequency['value'],
                'count' => (int) $frequency['count'],
                'percentage' => (float) $frequency['percentage'],
                'question' => $row['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validationSummary(Survey $survey): array
    {
        $round = $survey->validationRounds->sortByDesc('created_at')->first();

        if (! $round) {
            return [
                'round_count' => 0,
                'round' => null,
                'assigned_count' => 0,
                'submitted_count' => 0,
                'average_score' => null,
                'percentage' => null,
                'category' => 'N/A',
                'aspect_summary' => [],
                'decision_counts' => [],
                'revision_suggestions' => [],
                'has_submissions' => false,
                'empty_state' => 'Belum ada hasil validasi ahli. Data akan muncul setelah validator mengirim penilaian.',
            ];
        }

        $result = $this->validationResultService->analyze($round);

        return [
            'round_count' => $survey->validationRounds->count(),
            'round' => $round,
            'assigned_count' => $result->summary['assigned_count'],
            'submitted_count' => $result->summary['submitted_count'],
            'average_score' => $result->summary['overall_average_score'],
            'percentage' => $result->summary['percentage_feasibility'],
            'category' => $result->summary['validation_category'],
            'aspect_summary' => $result->aspectSummary,
            'decision_counts' => $result->summary['decision_counts'],
            'revision_suggestions' => collect($result->comments)->flatMap(fn (array $row): array => $row['comments'])->take(8)->values()->all(),
            'has_submissions' => (int) $result->summary['submitted_count'] > 0,
            'empty_state' => 'Belum ada hasil validasi ahli. Data akan muncul setelah validator mengirim penilaian.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readabilitySummary(Survey $survey): array
    {
        $round = $survey->readabilityRounds->sortByDesc('created_at')->first();

        if (! $round) {
            return [
                'round_count' => 0,
                'round' => null,
                'target_participants' => 0,
                'participant_count' => 0,
                'submitted_count' => 0,
                'average_score' => null,
                'category' => 'N/A',
                'confusing_item_count' => 0,
                'issue_type_counts' => [],
                'flagged_questions' => [],
                'revision_suggestions' => [],
                'has_submissions' => false,
                'empty_state' => 'Belum ada hasil uji keterbacaan. Data akan muncul setelah responden pilot mengirim feedback.',
            ];
        }

        $result = $this->readabilityResultService->analyze($round);

        return [
            'round_count' => $survey->readabilityRounds->count(),
            'round' => $round,
            'target_participants' => $round->target_participants ?? 0,
            'participant_count' => $result['summary']['participant_count'],
            'submitted_count' => $result['summary']['submitted_count'],
            'average_score' => $result['summary']['average_readability_score'],
            'category' => $result['summary']['category'],
            'confusing_item_count' => $result['summary']['confusing_item_count'],
            'issue_type_counts' => $result['issue_type_counts'],
            'flagged_questions' => $result['flagged_questions'],
            'revision_suggestions' => $result['revision_matrix'],
            'has_submissions' => (int) $result['summary']['submitted_count'] > 0,
            'empty_state' => 'Belum ada hasil uji keterbacaan. Data akan muncul setelah responden pilot mengirim feedback.',
        ];
    }

    /**
     * @param  Collection<int, SurveyResponse>  $responses
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>  $readability
     * @param  Collection<int, AnalysisSynthesisItem>  $items
     * @return array<string, mixed>
     */
    private function readiness(Collection $responses, array $validation, array $readability, Collection $items): array
    {
        $criteria = [
            ['label' => 'Main survey has responses', 'complete' => $responses->isNotEmpty()],
            ['label' => 'Expert validation submitted', 'complete' => $validation['has_submissions']],
            ['label' => 'Readability submitted', 'complete' => $readability['has_submissions']],
            ['label' => 'Synthesis matrix has accepted items', 'complete' => $items->where('status', AnalysisSynthesisItem::STATUS_ACCEPTED)->isNotEmpty()],
        ];
        $completeCount = collect($criteria)->where('complete', true)->count();
        $status = match (true) {
            $completeCount === count($criteria) => 'Ready for Design',
            $completeCount > 0 => 'Partially Ready',
            default => 'Not Ready',
        };

        return [
            'status' => $status,
            'short_label' => match ($status) {
                'Ready for Design' => 'Yes',
                'Partially Ready' => 'Partial',
                default => 'No',
            },
            'criteria' => $criteria,
            'complete_count' => $completeCount,
        ];
    }

    /**
     * @param  Collection<int, AnalysisSynthesisItem>  $items
     * @return array<string, array<int, string>>
     */
    private function filters(Collection $items): array
    {
        return [
            'source_type' => $items->pluck('source_type')->filter()->unique()->values()->all(),
            'theme' => $items->pluck('theme')->filter()->unique()->values()->all(),
            'priority_level' => $items->pluck('priority_level')->filter()->unique()->values()->all(),
            'mapped_module' => $items->pluck('mapped_module')->filter()->unique()->values()->all(),
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $priority
     * @return array<int, array<string, mixed>>
     */
    private function draftsFromPriority(Survey $survey, array $priority): array
    {
        $drafts = [];

        foreach (array_slice($priority['features'], 0, 5) as $row) {
            $drafts[] = [
                'source_type' => AnalysisSynthesisItem::SOURCE_STUDENT_SURVEY,
                'source_label' => 'Priority feature questionnaire',
                'theme' => AnalysisSynthesisItem::THEME_FEATURE_PRIORITY,
                'finding' => 'Fitur '.$row['label'].' menjadi prioritas responden.',
                'evidence_summary' => $row['question'],
                'evidence_metric' => $row['count'].' selected / '.number_format($row['percentage'], 2).'%',
                'priority_level' => $row['percentage'] >= 60 ? AnalysisSynthesisItem::PRIORITY_HIGH : AnalysisSynthesisItem::PRIORITY_MEDIUM,
                'design_implication' => 'Masukkan fitur ini dalam rancangan MVP PharmVR jika sesuai dengan tujuan pembelajaran.',
                'development_decision' => 'Petakan kebutuhan ini ke backlog Design dan Development PharmVR.',
                'mapped_module' => $this->mappedModule((string) $row['label']),
            ];
        }

        foreach (array_slice($priority['scenes'], 0, 5) as $row) {
            $drafts[] = [
                'source_type' => AnalysisSynthesisItem::SOURCE_STUDENT_SURVEY,
                'source_label' => 'Priority scene questionnaire',
                'theme' => AnalysisSynthesisItem::THEME_SCENE_PRIORITY,
                'finding' => 'Scene '.$row['label'].' perlu diprioritaskan dalam rancangan PharmVR.',
                'evidence_summary' => $row['question'],
                'evidence_metric' => $row['count'].' selected / '.number_format($row['percentage'], 2).'%',
                'priority_level' => $row['percentage'] >= 60 ? AnalysisSynthesisItem::PRIORITY_HIGH : AnalysisSynthesisItem::PRIORITY_MEDIUM,
                'design_implication' => 'Rancang alur visual dan interaksi VR untuk scene ini.',
                'development_decision' => 'Susun scope scene, aset, dan evaluasi awal.',
                'mapped_module' => $this->mappedModule((string) $row['label']),
            ];
        }

        return $drafts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $responseSummary
     * @return array<int, array<string, mixed>>
     */
    private function draftsFromResponses(Survey $survey, array $responseSummary): array
    {
        return collect($responseSummary)
            ->filter(fn (array $row): bool => isset($row['analysis']['mean']) && (float) $row['analysis']['mean'] >= 4.0 && $this->matches(Str::lower($row['label']), ['sulit', 'kesulitan', 'difficulty', 'cpob', 'gmp']))
            ->take(5)
            ->map(fn (array $row): array => [
                'source_type' => AnalysisSynthesisItem::SOURCE_STUDENT_SURVEY,
                'source_label' => 'Difficulty item',
                'theme' => AnalysisSynthesisItem::THEME_LEARNING_PROBLEM,
                'finding' => 'Mahasiswa mengalami kesulitan memahami '.$row['label'].'.',
                'evidence_summary' => $row['summary_text'],
                'evidence_metric' => 'Mean '.$this->format($row['analysis']['mean']),
                'priority_level' => AnalysisSynthesisItem::PRIORITY_HIGH,
                'design_implication' => 'Perlu visualisasi/interaksi VR pada modul terkait.',
                'development_decision' => 'Prioritaskan prototyping visual untuk topik ini.',
                'mapped_module' => $this->mappedModule($row['label']),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function draftsFromRelatedInstruments(Survey $survey): array
    {
        $related = Survey::query()
            ->with(['project', 'questions.answers.response', 'responses.answers'])
            ->where('project_id', $survey->project_id)
            ->where('parent_survey_id', $survey->getKey())
            ->whereIn('instrument_type', [Survey::INSTRUMENT_ANALYSIS_LECTURER, Survey::INSTRUMENT_PRACTITIONER_INTERVIEW])
            ->get();

        return $related
            ->flatMap(function (Survey $instrument): array {
                $submittedResponses = $instrument->responses
                    ->filter(fn (SurveyResponse $response): bool => $response->status === SurveyResponse::STATUS_SUBMITTED
                        && ! $response->is_test_response
                        && ! $response->excluded_from_analysis)
                    ->values();

                if ($submittedResponses->isEmpty()) {
                    return [];
                }

                $summary = $this->responseSummary($instrument, $submittedResponses);

                return $instrument->instrument_type === Survey::INSTRUMENT_ANALYSIS_LECTURER
                    ? $this->draftsFromLecturerInstrument($instrument, $summary)
                    : $this->draftsFromPractitionerInstrument($instrument, $summary);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $summary
     * @return array<int, array<string, mixed>>
     */
    private function draftsFromLecturerInstrument(Survey $instrument, array $summary): array
    {
        $drafts = [];

        foreach ($summary as $row) {
            $label = Str::lower($row['label']);
            $analysis = $row['analysis'];

            if (isset($analysis['mean']) && (float) $analysis['mean'] >= 4.0) {
                $theme = match (true) {
                    $this->matches($label, ['cpl', 'cpmk', 'obe', 'kurikulum']) => AnalysisSynthesisItem::THEME_ASSESSMENT_NEED,
                    $this->matches($label, ['assessment', 'pretest', 'posttest', 'rubrik', 'monitoring', 'tracking', 'dashboard']) => AnalysisSynthesisItem::THEME_ASSESSMENT_NEED,
                    $this->matches($label, ['teknologi', 'headset', 'laptop', 'smartphone', 'avatar', 'instructor']) => AnalysisSynthesisItem::THEME_TECHNOLOGY_READINESS,
                    $this->matches($label, ['konten', 'cpob', 'gmp', 'hygiene', 'gowning', 'airlock', 'weighing']) => AnalysisSynthesisItem::THEME_CPOB_CONTENT_NEED,
                    default => AnalysisSynthesisItem::THEME_VR_MEDIA_NEED,
                };

                $drafts[] = [
                    'source_type' => AnalysisSynthesisItem::SOURCE_LECTURER_SURVEY,
                    'source_label' => $instrument->title,
                    'theme' => $theme,
                    'finding' => 'Dosen menilai penting: '.$row['label'],
                    'evidence_summary' => $row['summary_text'],
                    'evidence_metric' => 'Mean '.$this->format($analysis['mean']),
                    'priority_level' => (float) $analysis['mean'] >= 4.5 ? AnalysisSynthesisItem::PRIORITY_HIGH : AnalysisSynthesisItem::PRIORITY_MEDIUM,
                    'design_implication' => 'Masukkan kebutuhan dosen ini ke rancangan pembelajaran PharmVR.',
                    'development_decision' => 'Petakan kebutuhan ke scene, fitur monitoring, atau assessment sesuai tema.',
                    'mapped_module' => $this->mappedModule($row['label']),
                ];
            }

            if (($row['type'] ?? null) === SurveyQuestion::TYPE_MULTIPLE_CHOICE && $this->matches($label, ['prioritas scene'])) {
                foreach (array_slice($this->frequencyRows($row), 0, 5) as $frequency) {
                    $drafts[] = [
                        'source_type' => AnalysisSynthesisItem::SOURCE_LECTURER_SURVEY,
                        'source_label' => $instrument->title,
                        'theme' => AnalysisSynthesisItem::THEME_SCENE_PRIORITY,
                        'finding' => 'Dosen memprioritaskan scene '.$frequency['label'].'.',
                        'evidence_summary' => $frequency['question'],
                        'evidence_metric' => $frequency['count'].' selected / '.number_format($frequency['percentage'], 2).'%',
                        'priority_level' => AnalysisSynthesisItem::PRIORITY_HIGH,
                        'design_implication' => 'Pertimbangkan scene ini sebagai kandidat prioritas desain PharmVR.',
                        'development_decision' => 'Masukkan scene ini ke backlog MVP bila selaras dengan validasi praktisi.',
                        'mapped_module' => $this->mappedModule((string) $frequency['label']),
                    ];
                }
            }
        }

        return array_slice($drafts, 0, 12);
    }

    /**
     * @param  array<int, array<string, mixed>>  $summary
     * @return array<int, array<string, mixed>>
     */
    private function draftsFromPractitionerInstrument(Survey $instrument, array $summary): array
    {
        $drafts = [];

        foreach ($summary as $row) {
            $label = Str::lower($row['label']);

            if (($row['type'] ?? null) === SurveyQuestion::TYPE_MULTIPLE_CHOICE && $this->matches($label, ['tema utama'])) {
                foreach (array_slice($this->frequencyRows($row), 0, 8) as $frequency) {
                    $drafts[] = [
                        'source_type' => AnalysisSynthesisItem::SOURCE_PRACTITIONER_INTERVIEW,
                        'source_label' => $instrument->title,
                        'theme' => $this->matches(Str::lower((string) $frequency['label']), ['risiko', 'miskonsepsi']) ? AnalysisSynthesisItem::THEME_DEVELOPMENT_RISK : AnalysisSynthesisItem::THEME_CPOB_CONTENT_NEED,
                        'finding' => 'Praktisi menekankan tema '.$frequency['label'].' dalam rancangan PharmVR.',
                        'evidence_summary' => $frequency['question'],
                        'evidence_metric' => $frequency['count'].' coded / '.number_format($frequency['percentage'], 2).'%',
                        'priority_level' => AnalysisSynthesisItem::PRIORITY_HIGH,
                        'design_implication' => 'Tema praktisi ini perlu dipakai untuk menjaga akurasi dan relevansi industri.',
                        'development_decision' => 'Gunakan tema ini sebagai checklist validasi scene dan konten.',
                        'mapped_module' => $this->mappedModule((string) $frequency['label']),
                    ];
                }
            }

            if (($row['type'] ?? null) === SurveyQuestion::TYPE_LONG_TEXT && $this->matches($label, ['risiko', 'scene', 'dokumentasi', 'weighing', 'airlock', 'evaluasi'])) {
                $sample = collect($row['analysis']['sample_answers'] ?? [])->first();

                if ($sample) {
                    $drafts[] = [
                        'source_type' => AnalysisSynthesisItem::SOURCE_PRACTITIONER_INTERVIEW,
                        'source_label' => $instrument->title,
                        'theme' => $this->matches($label, ['risiko']) ? AnalysisSynthesisItem::THEME_DEVELOPMENT_RISK : AnalysisSynthesisItem::THEME_CPOB_CONTENT_NEED,
                        'finding' => 'Masukan praktisi: '.$row['label'],
                        'evidence_summary' => $sample,
                        'evidence_metric' => $row['answered_count'].' interview notes',
                        'priority_level' => AnalysisSynthesisItem::PRIORITY_HIGH,
                        'design_implication' => 'Gunakan masukan praktisi untuk memperjelas akurasi scene dan materi.',
                        'development_decision' => 'Masukkan sebagai acceptance checklist untuk modul terkait.',
                        'mapped_module' => $this->mappedModule($row['label'].' '.$sample),
                    ];
                }
            }
        }

        return array_slice($drafts, 0, 12);
    }

    /**
     * @param  array<string, mixed>  $validation
     * @return array<int, array<string, mixed>>
     */
    private function draftsFromValidation(Survey $survey, array $validation): array
    {
        if (! $validation['has_submissions']) {
            return [];
        }

        return [[
            'source_type' => AnalysisSynthesisItem::SOURCE_EXPERT_VALIDATION,
            'source_label' => $validation['round']?->title,
            'theme' => AnalysisSynthesisItem::THEME_EXPERT_REVISION,
            'finding' => 'Instrumen dinilai '.$validation['category'].' oleh validator ahli.',
            'evidence_summary' => 'Submitted validators: '.$validation['submitted_count'].' of '.$validation['assigned_count'],
            'evidence_metric' => 'Average '.$this->format($validation['average_score']),
            'priority_level' => AnalysisSynthesisItem::PRIORITY_HIGH,
            'design_implication' => 'Instrumen dapat digunakan setelah revisi sesuai masukan validator.',
            'development_decision' => 'Gunakan indikator valid sebagai dasar kebutuhan Design PharmVR.',
        ]];
    }

    /**
     * @param  array<string, mixed>  $readability
     * @return array<int, array<string, mixed>>
     */
    private function draftsFromReadability(Survey $survey, array $readability): array
    {
        if (! $readability['has_submissions']) {
            return [];
        }

        return [[
            'source_type' => AnalysisSynthesisItem::SOURCE_READABILITY_TEST,
            'source_label' => $readability['round']?->title,
            'theme' => AnalysisSynthesisItem::THEME_USABILITY_READABILITY,
            'finding' => 'Beberapa responden pilot menilai item tertentu perlu diperjelas.',
            'evidence_summary' => 'Confusing item count: '.$readability['confusing_item_count'],
            'evidence_metric' => 'Average '.$this->format($readability['average_score']),
            'priority_level' => $readability['confusing_item_count'] > 0 ? AnalysisSynthesisItem::PRIORITY_HIGH : AnalysisSynthesisItem::PRIORITY_MEDIUM,
            'design_implication' => 'Revisi bahasa instrumen sebelum penyebaran utama.',
            'development_decision' => 'Gunakan masukan keterbacaan untuk memperjelas istilah CPOB/GMP pada materi PharmVR.',
        ]];
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function matches(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($text, Str::lower($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function mappedModule(string $label): ?string
    {
        $normalized = Str::lower($label);

        return match (true) {
            str_contains($normalized, 'lobby') => 'Lobby',
            str_contains($normalized, 'hygiene') => 'Hygiene',
            str_contains($normalized, 'gown') => 'Gowning',
            str_contains($normalized, 'airlock') => 'Airlock',
            str_contains($normalized, 'corridor') || str_contains($normalized, 'koridor') => 'Production Corridor',
            str_contains($normalized, 'weigh') || str_contains($normalized, 'timbang') => 'Weighing',
            str_contains($normalized, 'granulation') || str_contains($normalized, 'granulasi') => 'Granulation',
            str_contains($normalized, 'tablet') => 'Tabletting',
            str_contains($normalized, 'coating') => 'Coating',
            str_contains($normalized, 'pack') || str_contains($normalized, 'kemas') => 'Packaging',
            str_contains($normalized, 'qc') => 'QC Lab',
            str_contains($normalized, 'qa') => 'QA Office',
            str_contains($normalized, 'warehouse') || str_contains($normalized, 'gudang') => 'Warehouse',
            str_contains($normalized, 'pretest') || str_contains($normalized, 'posttest') || str_contains($normalized, 'assessment') => 'Assessment',
            str_contains($normalized, 'dashboard') || str_contains($normalized, 'progress') => 'Dashboard',
            default => null,
        };
    }

    private function format(mixed $value): string
    {
        return $value === null ? 'N/A' : number_format((float) $value, 2);
    }
}
