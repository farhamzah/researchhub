<?php

namespace App\Modules\Analysis\Services;

use App\Models\AnalysisCollectionTarget;
use App\Models\AnalysisDocumentPackage;
use App\Models\AnalysisPreflightReview;
use App\Models\AnalysisSynthesisItem;
use App\Models\Survey;
use App\Models\SurveyDistributionBatch;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyReadabilityRound;
use App\Models\SurveyValidationAssignment;
use App\Models\User;
use App\Modules\Surveys\Services\SurveyDistributionCenterService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AnalysisPreflightQaService
{
    public const SCOPE_STUDENT_QUESTIONNAIRE = 'student_questionnaire';

    public const SCOPE_DISTRIBUTION = 'distribution';

    public const SCOPE_FULL_ANALYSIS_PACKAGE = 'full_analysis_package';

    public const APPROVED_STUDENT_KEYS = [
        'A1', 'A2', 'A3',
        'B1', 'B2', 'B3', 'B4', 'B5',
        'C1', 'C2', 'C3', 'C4', 'C5', 'C6', 'C7',
        'D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7',
        'E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'E7',
        'F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7',
        'G1', 'G2',
        'H1', 'H2', 'H3', 'H4', 'H5',
    ];

    public const OFFICIAL_STUDENT_OPEN_KEYS = ['H1', 'H2', 'H3', 'H4', 'H5'];

    public const OBSOLETE_STUDENT_KEYS = ['G3', 'G4', 'G5'];

    public function __construct(
        private readonly AnalysisCollectionMonitoringService $collectionMonitoringService,
        private readonly SurveyDistributionCenterService $distributionCenterService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Survey $survey, User $user, string $scope = self::SCOPE_STUDENT_QUESTIONNAIRE): array
    {
        $scope = $this->normalizeScope($scope);
        $survey->load([
            'project',
            'pages.questions',
            'questions.scoring',
            'responses',
            'validationRounds.assignments.validator',
            'readabilityRounds.participants.response',
            'distributionBatches.recipients',
            'synthesisItems',
            'analysisDocumentPackage',
        ]);

        $instruments = $this->analysisInstruments($survey);
        $collection = $this->collectionMonitoringService->build($survey, $user);
        $distribution = $this->distributionCenterService->build($survey, $user);

        $checks = collect()
            ->merge($this->studentChecks($survey, $instruments['student']))
            ->merge($this->lecturerChecks($survey, $instruments['lecturer']))
            ->merge($this->practitionerChecks($survey, $instruments['practitioner']))
            ->merge($this->publicLinkChecks($survey, $instruments))
            ->merge($this->validationChecks($survey, $collection))
            ->merge($this->readabilityChecks($survey, $collection))
            ->merge($this->distributionChecks($survey, $distribution))
            ->merge($this->collectionChecks($survey, $collection))
            ->merge($this->packageChecks($survey))
            ->merge($this->synthesisChecks($survey))
            ->values();
        $checks = $this->applyScope($checks, $scope)->values();

        $summary = $this->summary($checks);

        return [
            'checks' => $checks->all(),
            'grouped_checks' => $checks->groupBy('source_type')->all(),
            'critical_issues' => $checks->where('severity', 'critical')->where('status', 'failed')->values()->all(),
            'warnings' => $checks->where('status', 'warning')->values()->all(),
            'passed_checks' => $checks->where('status', 'passed')->values()->all(),
            'summary' => $summary,
            'scope' => $scope,
            'scopes' => $this->scopes(),
            'student_readiness' => $this->studentReadiness($survey),
            'overall_status' => $summary['overall_status'],
            'can_mark_ready' => $summary['critical_failed'] === 0,
            'latest_review' => $survey->analysisPreflightReviews()->latest()->first(),
            'nav' => $this->nav($survey),
            'collection' => $collection,
            'generated_at' => now(),
        ];
    }

    /**
     * @return array{review: AnalysisPreflightReview, qa: array<string, mixed>}
     */
    public function markReady(
        Survey $survey,
        User $user,
        ?string $notes = null,
        string $scope = self::SCOPE_STUDENT_QUESTIONNAIRE,
    ): array {
        $qa = $this->build($survey, $user, $scope);

        if (! $qa['can_mark_ready']) {
            abort(422, 'Preflight QA still has critical failures.');
        }

        $summary = $qa['summary'];
        $review = AnalysisPreflightReview::create([
            'survey_id' => $survey->getKey(),
            'project_id' => $survey->project_id,
            'status' => AnalysisPreflightReview::STATUS_READY,
            'total_checks' => $summary['total'],
            'passed_checks' => $summary['passed'],
            'warning_checks' => $summary['warnings'],
            'failed_checks' => $summary['critical_failed'],
            'reviewed_by' => $user->getKey(),
            'reviewed_at' => now(),
            'ready_marked_at' => now(),
            'notes' => $notes,
            'snapshot_json' => [
                'overall_status' => $summary['overall_status'],
                'scope' => $qa['scope'],
                'checks' => $qa['checks'],
                'generated_at' => now()->toISOString(),
            ],
        ]);

        return ['review' => $review, 'qa' => $qa];
    }

    /**
     * @return array{added: int, skipped: int}
     */
    public function fixStudentSectionG(Survey $survey): array
    {
        $missing = $this->missingApprovedStudentKeys($survey);

        return ['added' => 0, 'skipped' => count(self::APPROVED_STUDENT_KEYS) - count($missing)];
    }

    /**
     * @return array{removed: int, blocked: int}
     */
    public function removeObsoleteStudentKeys(Survey $survey): array
    {
        $obsolete = $survey->questions()
            ->whereIn('question_key', self::OBSOLETE_STUDENT_KEYS);
        $count = (clone $obsolete)->count();

        if ($survey->responses()->exists()) {
            return ['removed' => 0, 'blocked' => $count];
        }

        $removed = $obsolete->delete();

        return ['removed' => $removed, 'blocked' => 0];
    }

    /**
     * @return array<string, Survey|null>
     */
    private function analysisInstruments(Survey $survey): array
    {
        $groupKey = $survey->analysis_group_key ?: Survey::ANALYSIS_GROUP_PHARMVR_ADDIE;
        $related = Survey::query()
            ->with(['pages.questions', 'questions.scoring', 'responses'])
            ->where('project_id', $survey->project_id)
            ->where(function ($query) use ($survey, $groupKey): void {
                $query
                    ->where('id', $survey->getKey())
                    ->orWhere('parent_survey_id', $survey->getKey())
                    ->orWhere('analysis_group_key', $groupKey);
            })
            ->get();

        return [
            'student' => $related->firstWhere('id', $survey->getKey()) ?: $survey,
            'lecturer' => $related->firstWhere('instrument_type', Survey::INSTRUMENT_ANALYSIS_LECTURER),
            'practitioner' => $related->firstWhere('instrument_type', Survey::INSTRUMENT_PRACTITIONER_INTERVIEW),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function studentChecks(Survey $mainSurvey, ?Survey $student): array
    {
        $checks = $this->baseInstrumentChecks($mainSurvey, $student, 'student_questionnaire', 'Student Questionnaire', true);

        if (! $student) {
            return $checks;
        }

        $missingKeys = $this->missingApprovedStudentKeys($student);
        $obsoleteKeys = $this->obsoleteStudentKeys($student);
        $openMissing = collect(self::OFFICIAL_STUDENT_OPEN_KEYS)->diff($student->questions->pluck('question_key'))->values()->all();
        $missingScoring = $this->missingStudentScoring($student);
        $consentValid = $this->studentConsentValid($student);
        $priorityValid = $this->studentPriorityQuestionsValid($student);
        $f6Valid = $this->studentRiskItemValid($student);

        $checks[] = $this->check(
            'student.final_43_keys',
            'Approved 43 student questionnaire keys',
            'student_questionnaire',
            'critical',
            $missingKeys === [],
            'Approved 43-key student structure is present.',
            'Student questionnaire is missing approved keys: '.implode(', ', $missingKeys),
            route('admin.surveys.builder.index', ['survey' => $student]),
            'Open Builder',
        );
        $checks[] = $this->check('student.section_h_open_feedback', 'Official Section H open-ended feedback', 'student_questionnaire', 'critical', $openMissing === [], 'H1-H5 open-ended feedback items are present.', 'Missing official Section H open feedback keys: '.implode(', ', $openMissing), route('admin.surveys.builder.index', ['survey' => $student]), 'Open Builder');

        $obsoleteFixUrl = null;
        $obsoleteFixLabel = null;
        if ($obsoleteKeys !== []) {
            $obsoleteFixUrl = $student->responses->isEmpty()
                ? route('admin.surveys.preflight.remove-obsolete-student-keys', ['survey' => $student])
                : route('admin.surveys.builder.index', ['survey' => $student]);
            $obsoleteFixLabel = $student->responses->isEmpty()
                ? 'Remove Obsolete Keys'
                : 'Open Builder';
        }

        $checks[] = $this->check(
            'student.no_obsolete_g_open_questions',
            'No obsolete G3-G5 extra keys',
            'student_questionnaire',
            'warning',
            $obsoleteKeys === [],
            'No obsolete G3-G5 keys found.',
            $student->responses->isEmpty()
                ? 'Obsolete extra keys found: '.implode(', ', $obsoleteKeys).'. Safe removal is available because there are no responses.'
                : 'Obsolete keys exist but cannot be removed because responses exist.',
            $obsoleteFixUrl,
            $obsoleteFixLabel,
        );
        $checks[] = $this->check('student.no_missing_scoring', 'No missing scoring for scoreable student items', 'student_questionnaire', 'critical', $missingScoring === [], 'Scoreable student questions have scoring configured.', 'Missing scoring for: '.implode(', ', $missingScoring), route('admin.surveys.scoring.index', ['survey' => $student]), 'Open Scoring');
        $checks[] = $this->check('student.consent_valid', 'Consent items render as required consent', 'student_questionnaire', 'critical', $consentValid, 'A1 and A3 are required consent questions.', 'Set A1 and A3 to required consent questions.', route('admin.surveys.builder.index', ['survey' => $student]), 'Open Builder');
        $checks[] = $this->check('student.g_priority_max_three', 'G1/G2 max 3 selections', 'student_questionnaire', 'critical', $priorityValid, 'G1 and G2 are required multiple choice questions with max 3 selections.', 'Set G1 and G2 to max 3 multiple-choice priority questions.', route('admin.surveys.builder.index', ['survey' => $student]), 'Open Builder');
        $checks[] = $this->check('student.f6_risk_descriptive', 'F6 risk item is descriptive', 'student_questionnaire', 'critical', $f6Valid, 'F6 is configured as descriptive risk item, not positive readiness scoring.', 'Set F6 scoring to descriptive/risk and exclude it from positive readiness aggregation.', route('admin.surveys.scoring.index', ['survey' => $student]), 'Open Scoring');

        return $checks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lecturerChecks(Survey $mainSurvey, ?Survey $lecturer): array
    {
        $checks = $this->baseInstrumentChecks($mainSurvey, $lecturer, 'lecturer_questionnaire', 'Lecturer Questionnaire', true);

        if (! $lecturer) {
            return $checks;
        }

        $requiredSections = [
            'identity' => ['identitas', 'identity'],
            'learning_needs' => ['kebutuhan', 'learning need'],
            'cpob_content' => ['cpob', 'gmp', 'content'],
            'curriculum_obe' => ['cpl', 'cpmk', 'obe', 'kurikulum'],
            'assessment_monitoring' => ['assessment', 'monitoring', 'evaluasi'],
            'technology_implementation' => ['teknologi', 'implementation', 'implementasi'],
            'priority_scene' => ['prioritas scene', 'scene'],
            'open_response' => ['saran', 'open', 'terbuka'],
        ];

        return array_merge($checks, $this->sectionKeywordChecks($mainSurvey, $lecturer, 'lecturer_questionnaire', $requiredSections));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function practitionerChecks(Survey $mainSurvey, ?Survey $practitioner): array
    {
        $checks = $this->baseInstrumentChecks($mainSurvey, $practitioner, 'practitioner_interview', 'Practitioner Interview Form', true);

        if (! $practitioner) {
            return $checks;
        }

        $requiredSections = [
            'identity' => ['identitas', 'identity', 'narasumber'],
            'core_interview' => ['wawancara', 'interview', 'pertanyaan inti'],
            'coding_theme' => ['tema', 'coding', 'kode'],
            'design_implication' => ['design', 'development', 'implikasi'],
        ];

        return array_merge($checks, $this->sectionKeywordChecks($mainSurvey, $practitioner, 'practitioner_interview', $requiredSections));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function baseInstrumentChecks(Survey $mainSurvey, ?Survey $instrument, string $sourceType, string $label, bool $criticalIfMissing): array
    {
        if (! $instrument) {
            return [
                $this->issue(
                    $sourceType.'.exists',
                    $label.' exists',
                    $sourceType,
                    $criticalIfMissing ? 'critical' : 'warning',
                    $label.' is missing.',
                    'Create the instrument from the Analysis Dashboard.',
                    route('admin.surveys.analysis.index', ['survey' => $mainSurvey]),
                    'Open Analysis Dashboard',
                ),
            ];
        }

        $checks = [
            $this->check($sourceType.'.title', $label.' title', $sourceType, 'critical', filled($instrument->title), 'Title is configured.', 'Fill the survey title in Builder.', route('admin.surveys.builder.index', ['survey' => $instrument]), 'Open Builder'),
            $this->check($sourceType.'.intro', $label.' intro text', $sourceType, 'critical', filled($instrument->intro_title) || filled($instrument->intro_text), 'Intro text is configured.', 'Add intro title/text in Builder.', route('admin.surveys.builder.index', ['survey' => $instrument]).'#intro', 'Open Builder'),
            $this->check($sourceType.'.privacy', $label.' privacy statement', $sourceType, 'critical', filled($instrument->privacy_statement), 'Privacy statement is configured.', 'Add privacy statement in Builder.', route('admin.surveys.builder.index', ['survey' => $instrument]).'#intro', 'Open Builder'),
            $this->check($sourceType.'.instruction', $label.' respondent instruction', $sourceType, 'critical', filled($instrument->respondent_instruction), 'Respondent instruction is configured.', 'Add respondent instructions in Builder.', route('admin.surveys.builder.index', ['survey' => $instrument]).'#intro', 'Open Builder'),
            $this->check($sourceType.'.duration', $label.' estimated duration', $sourceType, 'warning', filled($instrument->estimated_duration), 'Estimated duration is configured.', 'Add estimated completion time in Builder.', route('admin.surveys.builder.index', ['survey' => $instrument]).'#intro', 'Open Builder'),
            $this->check($sourceType.'.consent_text', $label.' consent text', $sourceType, 'critical', filled($instrument->consent_text), 'Consent text is configured.', 'Add consent text in Builder.', route('admin.surveys.builder.index', ['survey' => $instrument]).'#intro', 'Open Builder'),
            $this->check($sourceType.'.consent_gate', $label.' consent gate', $sourceType, 'warning', (bool) $instrument->require_consent_before_start, 'Consent gate is enabled.', 'Enable consent before start unless the method explicitly does not require it.', route('admin.surveys.builder.index', ['survey' => $instrument]).'#intro', 'Open Builder'),
            $this->check($sourceType.'.sections', $label.' sections/pages', $sourceType, 'warning', $instrument->pages->isNotEmpty(), 'Sections/pages are configured.', 'Create survey sections in Builder.', route('admin.surveys.builder.index', ['survey' => $instrument]), 'Open Builder'),
            $this->check($sourceType.'.questions', $label.' questions', $sourceType, 'critical', $instrument->questions->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)->isNotEmpty(), 'Questions are configured.', 'Add questions in Builder.', route('admin.surveys.builder.index', ['survey' => $instrument]), 'Open Builder'),
        ];

        return array_merge($checks, $this->questionIntegrityChecks($instrument, $sourceType, route('admin.surveys.builder.index', ['survey' => $instrument])));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function questionIntegrityChecks(Survey $instrument, string $sourceType, string $builderUrl): array
    {
        $visibleQuestions = $instrument->questions->where('type', '!=', SurveyQuestion::TYPE_HIDDEN);
        $emptyLabels = $visibleQuestions->filter(fn (SurveyQuestion $question): bool => blank($question->label))->count();
        $emptyOptions = $visibleQuestions->filter(fn (SurveyQuestion $question): bool => $this->hasInvalidOptions($question))->count();
        $duplicateLabels = $visibleQuestions
            ->map(fn (SurveyQuestion $question): string => $this->normalize((string) $question->label))
            ->filter()
            ->duplicates()
            ->unique()
            ->values();

        return [
            $this->check($sourceType.'.question_text', 'No empty question text', $sourceType, 'critical', $emptyLabels === 0, 'Question text is complete.', 'Fill every visible question label.', $builderUrl, 'Open Builder'),
            $this->check($sourceType.'.question_options', 'No empty or missing option labels', $sourceType, 'critical', $emptyOptions === 0, 'Choice/Likert options are complete.', 'Fill options for each choice, multiple choice, Likert, and matrix item.', $builderUrl, 'Open Builder'),
            $this->check($sourceType.'.duplicate_questions', 'No duplicate exact question text', $sourceType, 'warning', $duplicateLabels->isEmpty(), 'No duplicate exact question text found.', 'Review duplicate question text: '.$duplicateLabels->join(' | '), $builderUrl, 'Open Builder'),
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $requiredSections
     * @return array<int, array<string, mixed>>
     */
    private function sectionKeywordChecks(Survey $mainSurvey, Survey $instrument, string $sourceType, array $requiredSections): array
    {
        $text = $this->surveyText($instrument);

        return collect($requiredSections)
            ->map(fn (array $keywords, string $key): array => $this->check(
                $sourceType.'.section_'.$key,
                Str::title(str_replace('_', ' ', $key)).' section',
                $sourceType,
                'critical',
                $this->containsAny($text, $keywords),
                Str::title(str_replace('_', ' ', $key)).' section appears to be present.',
                'Add or rename section/questions so this required area is clear.',
                route('admin.surveys.builder.index', ['survey' => $instrument]),
                'Open Builder',
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, Survey|null>  $instruments
     * @return array<int, array<string, mixed>>
     */
    private function publicLinkChecks(Survey $mainSurvey, array $instruments): array
    {
        return collect($instruments)
            ->flatMap(function (?Survey $instrument, string $key) use ($mainSurvey): array {
                $sourceType = $key === 'student' ? 'student_questionnaire' : ($key === 'lecturer' ? 'lecturer_questionnaire' : 'practitioner_interview');
                $label = Str::title(str_replace('_', ' ', $sourceType));

                if (! $instrument) {
                    return [
                        $this->issue($sourceType.'.public_link', $label.' public link', $sourceType, 'critical', 'Public link cannot be checked because the instrument is missing.', 'Create and publish the instrument first.', route('admin.surveys.analysis.index', ['survey' => $mainSurvey]), 'Open Analysis Dashboard'),
                    ];
                }

                return [
                    $this->check($sourceType.'.public_route', $label.' public route exists', $sourceType, 'critical', Route::has('survey.show'), 'Public survey route exists.', 'Restore the public survey route.', null),
                    $this->check($sourceType.'.public_access', $label.' public access enabled', $sourceType, 'critical', $instrument->canReceiveResponses(), 'Survey is published and public.', 'Publish the survey and enable public access before sending links.', route('admin.surveys.builder.index', ['survey' => $instrument]), 'Open Builder'),
                    $this->check($sourceType.'.intro_gate', $label.' intro page ready', $sourceType, 'warning', blank($instrument->intro_text) || filled($instrument->intro_text), 'Intro page state is deterministic from survey metadata.', 'Review public preview before sending.', route('survey.show', ['survey' => $instrument->slug]), 'Open Public Link'),
                    $this->check($sourceType.'.questions_reachable', $label.' questions reachable', $sourceType, 'critical', $instrument->questions->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)->isNotEmpty(), 'Question page has visible questions.', 'Add visible questions before distribution.', route('admin.surveys.builder.index', ['survey' => $instrument]), 'Open Builder'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validationChecks(Survey $survey, array $collection): array
    {
        $rounds = $survey->validationRounds;
        $assignments = $rounds->flatMap->assignments;
        $submitted = $assignments->filter(fn (SurveyValidationAssignment $assignment): bool => $assignment->isSubmitted())->count();
        $target = $this->target($collection, AnalysisCollectionTarget::SOURCE_EXPERT_VALIDATION);

        return [
            $this->check('validation.workflow', 'Validation workflow exists', 'expert_validation', 'critical', Route::has('admin.surveys.validation.index'), 'Validation route exists.', 'Restore validation route.', null),
            $this->check('validation.round', 'Validation round exists', 'expert_validation', 'critical', $rounds->isNotEmpty(), 'Validation round exists.', 'Create validation round.', route('admin.surveys.validation.index', ['survey' => $survey]), 'Open Validation'),
            $this->check('validation.validators', 'Validators assigned', 'expert_validation', 'critical', $assignments->isNotEmpty(), 'At least one validator is assigned.', 'Assign validators before sending validation links.', route('admin.surveys.validation.index', ['survey' => $survey]), 'Open Validation'),
            $this->check('validation.minimum', 'Validator minimum target', 'expert_validation', 'warning', $target && $assignments->count() >= $target['minimum_count'], $assignments->count().' validators assigned; minimum '.$target['minimum_count'].'.', 'Add validators or adjust target in Collection Monitoring.', route('admin.surveys.collection-monitoring.index', ['survey' => $survey]), 'Open Collection Monitoring'),
            $this->check('validation.submissions', 'Validation submitted count', 'expert_validation', 'warning', ! $target || $submitted >= $target['minimum_count'], $submitted.' validators submitted.', 'Follow up pending validators.', route('admin.surveys.validation.index', ['survey' => $survey]), 'Open Validation'),
            $this->check('validation.report_route', 'Validation report route', 'expert_validation', 'info', Route::has('admin.surveys.validation.report'), 'Validation report route exists.', 'Restore validation report route.', null),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readabilityChecks(Survey $survey, array $collection): array
    {
        $rounds = $survey->readabilityRounds;
        $openRounds = $rounds->where('status', SurveyReadabilityRound::STATUS_OPEN);
        $participants = $rounds->flatMap->participants;
        $submitted = $participants->filter(fn (SurveyReadabilityParticipant $participant): bool => $participant->isSubmitted())->count();
        $target = $this->target($collection, AnalysisCollectionTarget::SOURCE_READABILITY_TEST);

        return [
            $this->check('readability.workflow', 'Readability workflow exists', 'readability_test', 'critical', Route::has('admin.surveys.readability.index'), 'Readability route exists.', 'Restore readability route.', null),
            $this->check('readability.round', 'Readability round exists', 'readability_test', 'critical', $rounds->isNotEmpty(), 'Readability round exists.', 'Create readability round.', route('admin.surveys.readability.index', ['survey' => $survey]), 'Open Readability'),
            $this->check('readability.open_round', 'Active/open readability round', 'readability_test', 'critical', $openRounds->isNotEmpty(), 'Open readability round exists.', 'Open or create a readability round.', route('admin.surveys.readability.index', ['survey' => $survey]), 'Open Readability'),
            $this->check('readability.participants', 'Readability participants exist', 'readability_test', 'warning', $participants->isNotEmpty(), 'Readability participants exist.', 'Add readability participants.', route('admin.surveys.readability.index', ['survey' => $survey]), 'Open Readability'),
            $this->check('readability.minimum_participants', 'Readability participant minimum target', 'readability_test', 'warning', $target && $participants->count() >= $target['minimum_count'], $participants->count().' participants assigned; minimum '.$target['minimum_count'].'.', 'Add participants or adjust target.', route('admin.surveys.collection-monitoring.index', ['survey' => $survey]), 'Open Collection Monitoring'),
            $this->check('readability.submissions', 'Readability submitted count', 'readability_test', 'warning', ! $target || $submitted >= $target['minimum_count'], $submitted.' readability participants submitted.', 'Follow up pending readability participants.', route('admin.surveys.readability.index', ['survey' => $survey]), 'Open Readability'),
            $this->check('readability.report_route', 'Readability report route', 'readability_test', 'info', Route::has('admin.surveys.readability.report.latest'), 'Readability report route exists.', 'Restore readability report route.', null),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function distributionChecks(Survey $survey, array $distribution): array
    {
        $instrumentMessagesReady = collect($distribution['instruments'] ?? [])
            ->every(fn (array $panel): bool => filled($panel['whatsapp_message'] ?? null) && filled($panel['email_message'] ?? null));
        $validationMessagesReady = collect($distribution['validation']['rounds'] ?? [])->flatMap(fn (array $round): array => $round['assignments'] ?? [])
            ->every(fn (array $assignment): bool => filled($assignment['whatsapp_message'] ?? null) && filled($assignment['email_message'] ?? null));
        $readabilityMessagesReady = collect($distribution['readability']['rounds'] ?? [])->flatMap(fn (array $round): array => $round['participants'] ?? [])
            ->every(fn (array $participant): bool => filled($participant['whatsapp_message'] ?? null) && filled($participant['email_message'] ?? null));
        $batches = $survey->distributionBatches;

        return [
            $this->check('distribution.route', 'Distribution Center route exists', 'distribution', 'critical', Route::has('admin.surveys.distribution.index'), 'Distribution Center route exists.', 'Restore distribution route.', null),
            $this->check('distribution.instrument_messages', 'Student/lecturer/practitioner message templates', 'distribution', 'warning', $instrumentMessagesReady, 'Instrument invitation templates are available.', 'Review Distribution Center message templates.', route('admin.surveys.distribution.index', ['survey' => $survey]), 'Open Distribution Center'),
            $this->check('distribution.validator_messages', 'Validator message templates', 'distribution', 'warning', $validationMessagesReady, 'Validator invitation templates are available or no validators are assigned yet.', 'Assign validators and review invitation templates.', route('admin.surveys.distribution.index', ['survey' => $survey]), 'Open Distribution Center'),
            $this->check('distribution.readability_messages', 'Readability message templates', 'distribution', 'warning', $readabilityMessagesReady, 'Readability invitation templates are available or no participants are assigned yet.', 'Add participants and review invitation templates.', route('admin.surveys.distribution.index', ['survey' => $survey]), 'Open Distribution Center'),
            $this->check('distribution.supervisor_package', 'Supervisor package text', 'distribution', 'warning', filled($distribution['supervisor']['message'] ?? null), 'Supervisor package message exists.', 'Review supervisor package text.', route('admin.surveys.distribution.index', ['survey' => $survey]), 'Open Distribution Center'),
            $this->check('distribution.batches', 'Distribution batches configured', 'distribution', 'warning', $batches->isNotEmpty(), 'Distribution batches exist.', 'Configure distribution batches, status, and deadlines.', route('admin.surveys.distribution.index', ['survey' => $survey]), 'Open Distribution Center'),
            $this->check('distribution.deadline_status', 'Distribution deadline/status configured', 'distribution', 'warning', $batches->isNotEmpty() && $batches->every(fn (SurveyDistributionBatch $batch): bool => filled($batch->status) && $batch->deadline !== null), 'Distribution batch status and deadline are configured.', 'Add deadline and status for each distribution batch.', route('admin.surveys.distribution.index', ['survey' => $survey]), 'Open Distribution Center'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectionChecks(Survey $survey, array $collection): array
    {
        $sources = collect($collection['sources']);

        $checks = [
            $this->check('collection.route', 'Collection Monitoring route exists', 'collection_monitoring', 'critical', Route::has('admin.surveys.collection-monitoring.index'), 'Collection Monitoring route exists.', 'Restore collection monitoring route.', null),
            $this->check('collection.targets', 'All collection targets exist', 'collection_monitoring', 'critical', $sources->count() === count(AnalysisCollectionTarget::SOURCES), 'All collection target sources are available.', 'Open Collection Monitoring to create default targets.', route('admin.surveys.collection-monitoring.index', ['survey' => $survey]), 'Open Collection Monitoring'),
            $this->check('collection.readiness', 'Overall collection readiness calculated', 'collection_monitoring', 'info', filled($collection['readiness']['status'] ?? null), 'Collection readiness can be calculated.', 'Review Collection Monitoring.', route('admin.surveys.collection-monitoring.index', ['survey' => $survey]), 'Open Collection Monitoring'),
        ];

        foreach ($sources as $source) {
            $checks[] = $this->check(
                'collection.'.$source['source_type'].'.counts',
                $source['label'].' minimum and target counts',
                'collection_monitoring',
                'critical',
                $source['minimum_count'] > 0 && $source['target_count'] > 0,
                $source['label'].' target is '.$source['minimum_count'].' / '.$source['target_count'].'.',
                'Set minimum and target counts.',
                route('admin.surveys.collection-monitoring.index', ['survey' => $survey]),
                'Open Collection Monitoring',
            );
            $checks[] = $this->check(
                'collection.'.$source['source_type'].'.due_date',
                $source['label'].' due date',
                'collection_monitoring',
                'warning',
                $source['target']->due_date !== null,
                $source['label'].' due date is configured.',
                'Add a due date or document why no deadline is needed.',
                route('admin.surveys.collection-monitoring.index', ['survey' => $survey]),
                'Open Collection Monitoring',
                optional: true,
            );
        }

        return $checks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function packageChecks(Survey $survey): array
    {
        $package = $survey->analysisDocumentPackage;

        if (! $package) {
            return [
                $this->issue('package.exists', 'Analysis package metadata exists', 'analysis_package', 'warning', 'Analysis package metadata has not been created yet.', 'Open Analysis Package to create default metadata.', route('admin.surveys.analysis-package.index', ['survey' => $survey]), 'Open Analysis Package'),
            ];
        }

        return [
            $this->check('package.title', 'Document title exists', 'analysis_package', 'critical', filled($package->title), 'Document title exists.', 'Fill document title.', route('admin.surveys.analysis-package.index', ['survey' => $survey]), 'Open Analysis Package'),
            $this->check('package.researcher', 'Researcher name exists', 'analysis_package', 'warning', filled($package->researcher_name), 'Researcher name exists.', 'Fill researcher name.', route('admin.surveys.analysis-package.index', ['survey' => $survey]), 'Open Analysis Package'),
            $this->check('package.institution', 'Institution exists', 'analysis_package', 'warning', filled($package->institution), 'Institution exists.', 'Fill institution.', route('admin.surveys.analysis-package.index', ['survey' => $survey]), 'Open Analysis Package'),
            $this->check('package.stage', 'Stage exists', 'analysis_package', 'critical', filled($package->stage), 'Stage exists.', 'Fill stage.', route('admin.surveys.analysis-package.index', ['survey' => $survey]), 'Open Analysis Package'),
            $this->check('package.promoters', 'Promoter/co-promoter fields', 'analysis_package', 'warning', filled($package->promoter_name) && filled($package->co_promoter_names), 'Promoter/co-promoter metadata is filled.', 'Fill promoter and co-promoter fields before supervisor review.', route('admin.surveys.analysis-package.index', ['survey' => $survey]), 'Open Analysis Package', optional: true),
            $this->check('package.print_route', 'Package print route', 'analysis_package', 'info', Route::has('admin.surveys.analysis-package.print'), 'Print route exists.', 'Restore package print route.', null),
            $this->check('package.html_route', 'Package HTML export route', 'analysis_package', 'info', Route::has('admin.surveys.analysis-package.export-html'), 'HTML export route exists.', 'Restore package HTML export route.', null),
            $this->check('package.doc_route', 'Package DOC export route', 'analysis_package', 'info', Route::has('admin.surveys.analysis-package.export-doc'), 'DOC export route exists.', 'Restore package DOC export route.', null),
            $this->check('package.finalized', 'Package finalized snapshot', 'analysis_package', 'warning', $package->status === AnalysisDocumentPackage::STATUS_FINAL && $package->finalized_at !== null, 'Analysis package is finalized.', 'Finalize after metadata and package content have been reviewed.', route('admin.surveys.analysis-package.index', ['survey' => $survey]), 'Open Analysis Package', optional: true),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function synthesisChecks(Survey $survey): array
    {
        $items = $survey->synthesisItems;
        $accepted = $items->filter(fn (AnalysisSynthesisItem $item): bool => in_array($item->status, [
            AnalysisSynthesisItem::STATUS_ACCEPTED,
            AnalysisSynthesisItem::STATUS_IN_DESIGN,
        ], true));

        return [
            $this->check('synthesis.analysis_route', 'Analysis Dashboard route exists', 'synthesis_matrix', 'critical', Route::has('admin.surveys.analysis.index'), 'Analysis Dashboard route exists.', 'Restore Analysis Dashboard route.', null),
            $this->check('synthesis.items', 'Synthesis matrix exists', 'synthesis_matrix', 'warning', $items->isNotEmpty(), 'Synthesis items exist.', 'Generate or add synthesis items from Analysis Dashboard.', route('admin.surveys.analysis.index', ['survey' => $survey]), 'Open Analysis Dashboard'),
            $this->check('synthesis.accepted_items', 'Accepted or in-design synthesis items', 'synthesis_matrix', 'warning', $accepted->isNotEmpty(), 'Accepted/in-design synthesis items exist.', 'Review and accept synthesis items before supervisor package finalization.', route('admin.surveys.analysis.index', ['survey' => $survey]), 'Open Analysis Dashboard'),
            $this->check('synthesis.generate_action', 'Draft synthesis generation action', 'synthesis_matrix', 'info', Route::has('admin.surveys.analysis.generate-synthesis'), 'Generate Draft Synthesis action exists.', 'Use this action when enough data is available.', route('admin.surveys.analysis.index', ['survey' => $survey]), 'Open Analysis Dashboard'),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $checks
     * @return array<string, mixed>
     */
    private function summary(Collection $checks): array
    {
        $criticalFailed = $checks->where('severity', 'critical')->where('status', 'failed')->count();
        $warnings = $checks->where('status', 'warning')->count();
        $optionalWarnings = $checks->where('status', 'warning')->where('optional', true)->count();
        $overall = match (true) {
            $criticalFailed > 0 => 'Not Ready',
            $warnings === 0 => 'Ready to Send',
            $warnings === $optionalWarnings => 'Ready with Notes',
            default => 'Needs Attention',
        };

        return [
            'total' => $checks->count(),
            'passed' => $checks->where('status', 'passed')->count(),
            'warnings' => $warnings,
            'critical_failed' => $criticalFailed,
            'skipped' => $checks->where('status', 'skipped')->count(),
            'overall_status' => $overall,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function check(
        string $key,
        string $label,
        string $sourceType,
        string $severity,
        bool $passes,
        string $passMessage,
        string $recommendation,
        ?string $fixUrl = null,
        ?string $fixActionLabel = null,
        bool $optional = false,
    ): array {
        return [
            'check_key' => $key,
            'label' => $label,
            'source_type' => $sourceType,
            'severity' => $severity,
            'status' => $passes ? 'passed' : ($severity === 'critical' ? 'failed' : 'warning'),
            'message' => $passes ? $passMessage : $recommendation,
            'recommendation' => $passes ? 'No action needed.' : $recommendation,
            'related_url' => $fixUrl,
            'fix_action_label' => $fixActionLabel,
            'fix_url' => $fixUrl,
            'optional' => $optional,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(
        string $key,
        string $label,
        string $sourceType,
        string $severity,
        string $message,
        string $recommendation,
        ?string $fixUrl = null,
        ?string $fixActionLabel = null,
        bool $optional = false,
    ): array {
        return [
            'check_key' => $key,
            'label' => $label,
            'source_type' => $sourceType,
            'severity' => $severity,
            'status' => $severity === 'critical' ? 'failed' : 'warning',
            'message' => $message,
            'recommendation' => $recommendation,
            'related_url' => $fixUrl,
            'fix_action_label' => $fixActionLabel,
            'fix_url' => $fixUrl,
            'optional' => $optional,
        ];
    }

    private function hasInvalidOptions(SurveyQuestion $question): bool
    {
        if (! in_array($question->type, [
            SurveyQuestion::TYPE_SINGLE_CHOICE,
            SurveyQuestion::TYPE_MULTIPLE_CHOICE,
            SurveyQuestion::TYPE_LIKERT,
            SurveyQuestion::TYPE_LIKERT_MATRIX,
        ], true)) {
            return false;
        }

        $options = $question->options ?? [];
        $settings = $question->settings ?? [];
        $values = $options['choices'] ?? $options['options'] ?? $options['scale'] ?? $settings['scale'] ?? [];

        if ($question->type === SurveyQuestion::TYPE_LIKERT_MATRIX) {
            $values = array_merge($options['rows'] ?? [], $options['columns'] ?? $settings['scale'] ?? []);
        }

        if (! is_array($values) || $values === []) {
            return true;
        }

        return collect($values)->contains(fn (mixed $value): bool => blank(is_array($value) ? ($value['label'] ?? $value['value'] ?? null) : $value));
    }

    private function normalizeScope(string $scope): string
    {
        return in_array($scope, array_keys($this->scopes()), true) ? $scope : self::SCOPE_STUDENT_QUESTIONNAIRE;
    }

    /**
     * @return array<string, string>
     */
    private function scopes(): array
    {
        return [
            self::SCOPE_STUDENT_QUESTIONNAIRE => 'Student Questionnaire',
            'lecturer_questionnaire' => 'Lecturer Questionnaire',
            'practitioner_interview' => 'Practitioner Interview',
            'expert_validation' => 'Expert Validation',
            'readability_test' => 'Readability Test',
            self::SCOPE_DISTRIBUTION => 'Distribution',
            'analysis_package' => 'Analysis Package / Synthesis',
            self::SCOPE_FULL_ANALYSIS_PACKAGE => 'Full Analysis Package',
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $checks
     * @return Collection<int, array<string, mixed>>
     */
    private function applyScope(Collection $checks, string $scope): Collection
    {
        if ($scope === self::SCOPE_FULL_ANALYSIS_PACKAGE) {
            return $checks;
        }

        return $checks->map(function (array $check) use ($scope): array {
            $source = $check['source_type'] ?? '';

            if ($scope === self::SCOPE_STUDENT_QUESTIONNAIRE && $source !== self::SCOPE_STUDENT_QUESTIONNAIRE) {
                if (in_array($source, ['lecturer_questionnaire', 'practitioner_interview'], true)) {
                    return $this->demote($check, 'info', 'Pending other instruments: '.$check['message']);
                }

                if (in_array($source, ['expert_validation', 'readability_test'], true)) {
                    return $this->demote($check, 'warning', 'Next workflow step: '.$check['message']);
                }

                return $this->demote($check, 'warning', 'Pending later scope: '.$check['message']);
            }

            if ($scope === self::SCOPE_STUDENT_QUESTIONNAIRE && ($check['check_key'] ?? '') === 'student_questionnaire.public_access') {
                return $this->demote($check, 'warning', 'Distribution pending: '.$check['message']);
            }

            if ($scope === self::SCOPE_DISTRIBUTION) {
                if (
                    $source === self::SCOPE_DISTRIBUTION
                    || in_array($check['check_key'] ?? '', [
                        'student_questionnaire.public_access',
                        'lecturer_questionnaire.public_access',
                        'practitioner_interview.public_access',
                    ], true)
                ) {
                    return $check;
                }

                return $this->demote($check, 'warning', 'Pending earlier scope: '.$check['message']);
            }

            if ($scope !== $source) {
                return $this->demote($check, 'warning', 'Pending later scope: '.$check['message']);
            }

            return $check;
        });
    }

    /**
     * @param  array<string, mixed>  $check
     * @return array<string, mixed>
     */
    private function demote(array $check, string $severity, string $message): array
    {
        if (($check['status'] ?? null) !== 'failed') {
            return $check;
        }

        $check['severity'] = $severity;
        $check['status'] = 'warning';
        $check['message'] = $message;
        $check['recommendation'] = $message;

        return $check;
    }

    /**
     * @return array<string, mixed>
     */
    private function studentReadiness(Survey $survey): array
    {
        $missing = $this->missingApprovedStudentKeys($survey);
        $obsolete = $this->obsoleteStudentKeys($survey);

        return [
            'approved_present' => count(self::APPROVED_STUDENT_KEYS) - count($missing),
            'approved_total' => count(self::APPROVED_STUDENT_KEYS),
            'missing_keys' => $missing,
            'obsolete_keys' => $obsolete,
            'missing_scoring' => count($this->missingStudentScoring($survey)),
            'consent_valid' => $this->studentConsentValid($survey),
            'g_priority_valid' => $this->studentPriorityQuestionsValid($survey),
            'f6_risk_descriptive' => $this->studentRiskItemValid($survey),
            'public_access' => $survey->canReceiveResponses() ? 'enabled' : 'pending',
            'readability' => $survey->readabilityRounds->isNotEmpty() ? 'configured' : 'pending',
            'expert_validation' => $survey->validationRounds->isNotEmpty() ? 'configured' : 'pending',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function missingApprovedStudentKeys(Survey $student): array
    {
        return collect(self::APPROVED_STUDENT_KEYS)
            ->diff($student->questions->pluck('question_key')->filter()->values())
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function obsoleteStudentKeys(Survey $student): array
    {
        return $student->questions
            ->pluck('question_key')
            ->filter(fn (?string $key): bool => in_array((string) $key, self::OBSOLETE_STUDENT_KEYS, true))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function missingStudentScoring(Survey $student): array
    {
        return $student->questions
            ->filter(fn (SurveyQuestion $question): bool => in_array($question->type, [
                SurveyQuestion::TYPE_SINGLE_CHOICE,
                SurveyQuestion::TYPE_MULTIPLE_CHOICE,
                SurveyQuestion::TYPE_LIKERT,
                SurveyQuestion::TYPE_NUMBER,
            ], true))
            ->reject(fn (SurveyQuestion $question): bool => $question->scoring !== null)
            ->pluck('question_key')
            ->values()
            ->all();
    }

    private function studentConsentValid(Survey $student): bool
    {
        return collect(['A1', 'A3'])->every(function (string $key) use ($student): bool {
            $question = $student->questions->firstWhere('question_key', $key);

            return $question instanceof SurveyQuestion
                && $question->type === SurveyQuestion::TYPE_CONSENT
                && (bool) $question->is_required;
        });
    }

    private function studentPriorityQuestionsValid(Survey $student): bool
    {
        return collect(['G1', 'G2'])->every(function (string $key) use ($student): bool {
            $question = $student->questions->firstWhere('question_key', $key);

            return $question instanceof SurveyQuestion
                && $question->type === SurveyQuestion::TYPE_MULTIPLE_CHOICE
                && (bool) $question->is_required
                && (int) data_get($question->settings, 'max_selections') === 3;
        });
    }

    private function studentRiskItemValid(Survey $student): bool
    {
        $question = $student->questions->firstWhere('question_key', 'F6');

        return $question instanceof SurveyQuestion
            && $question->scoring !== null
            && ! $question->scoring->is_scored
            && (bool) data_get($question->scoring->settings, 'risk_item')
            && (bool) data_get($question->scoring->settings, 'not_positive_readiness');
    }

    private function target(array $collection, string $sourceType): ?array
    {
        return collect($collection['sources'])->firstWhere('source_type', $sourceType);
    }

    private function surveyText(Survey $survey): string
    {
        return Str::lower(collect()
            ->merge([$survey->title, $survey->description, $survey->intro_text, $survey->respondent_instruction])
            ->merge($survey->pages->flatMap(fn (SurveyPage $page): array => [$page->title, $page->description]))
            ->merge($survey->questions->pluck('label'))
            ->filter()
            ->join(' '));
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function containsAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($text, Str::lower($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', Str::lower($text)));
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
            'Analysis' => route('admin.surveys.analysis.index', ['survey' => $survey]),
            'Distribution' => route('admin.surveys.distribution.index', ['survey' => $survey]),
            'Collection Monitoring' => route('admin.surveys.collection-monitoring.index', ['survey' => $survey]),
            'Analysis Package' => route('admin.surveys.analysis-package.index', ['survey' => $survey]),
            'Preflight QA' => route('admin.surveys.preflight.index', ['survey' => $survey]),
            'Respondent Package' => route('admin.surveys.respondent-package.index', ['survey' => $survey]),
            'Back to Surveys' => route('filament.admin.resources.surveys.index'),
        ];
    }
}
