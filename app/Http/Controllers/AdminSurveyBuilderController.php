<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Models\SurveyValidationRound;
use App\Modules\AcademicOutputs\Services\AcademicNarrativeService;
use App\Modules\Surveys\Actions\CreateLecturerNeedsAnalysisQuestionnaireAction;
use App\Modules\Surveys\Actions\CreatePractitionerInterviewFormAction;
use App\Modules\Surveys\Actions\CreateSurveyPageAction;
use App\Modules\Surveys\Actions\CreateSurveyQuestionAction;
use App\Modules\Surveys\Actions\DeleteSurveyPageAction;
use App\Modules\Surveys\Actions\DeleteSurveyQuestionAction;
use App\Modules\Surveys\Actions\DuplicateSurveyQuestionAction;
use App\Modules\Surveys\Actions\UpdateSurveyIntroAction;
use App\Modules\Surveys\Actions\UpdateSurveyPageAction;
use App\Modules\Surveys\Actions\UpdateSurveyQuestionAction;
use App\Modules\Surveys\Services\PharmVrStudentNeedsSurveyTemplateService;
use App\Modules\Surveys\Services\SurveyBuilderReadinessService;
use App\Modules\Surveys\Services\SurveyBulkQuestionImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSurveyBuilderController extends Controller
{
    public function index(
        Survey $survey,
        SurveyBuilderReadinessService $readiness,
        AcademicNarrativeService $academicNarratives,
        PharmVrStudentNeedsSurveyTemplateService $pharmVrTemplate,
    ): View {
        Gate::authorize('update', $survey);

        $survey->load([
            'project',
            'pages.questions.scoring.indicator',
            'questions.page',
            'questions.scoring.indicator.scale',
            'scales',
            'indicators.scale',
            'indicators.questionScorings.question',
            'validationRounds.assignments.scores',
            'analysisResults',
            'responses:id,survey_id,status,submitted_at',
        ])->loadCount([
            'responses',
            'responses as real_responses_count' => fn ($query) => $query->official(),
        ]);

        $latestValidationRound = $survey->validationRounds
            ->sortByDesc('created_at')
            ->first();

        return view('surveys.admin.builder.index', [
            'survey' => $survey,
            'builderWizard' => $readiness->build($survey),
            'academicNarratives' => [
                'surveyInstrument' => $academicNarratives->surveyInstrumentSummary($survey),
                'expertValidation' => $latestValidationRound instanceof SurveyValidationRound
                    ? $academicNarratives->expertValidationSummary($latestValidationRound)
                    : 'Ringkasan validasi ahli belum tersedia karena survey ini belum memiliki putaran validasi.',
                'surveyAnalysis' => $academicNarratives->surveyAnalysisSummary($survey),
            ],
            'pharmVrTemplatePreview' => $this->showsStudentTemplateActions($survey) ? $pharmVrTemplate->previewMissing($survey) : null,
            'pharmVrNormalizationPreview' => $this->showsStudentTemplateActions($survey) ? $pharmVrTemplate->previewNormalization($survey) : null,
            'templateActionScope' => $this->templateActionScope($survey),
            'questionTypes' => config('researchhub_surveys.question_types', []),
            'hasResponses' => $survey->responses_count > 0,
            'hasRealResponses' => $survey->real_responses_count > 0,
            'optionQuestionTypes' => [
                SurveyQuestion::TYPE_SINGLE_CHOICE,
                SurveyQuestion::TYPE_MULTIPLE_CHOICE,
                SurveyQuestion::TYPE_LIKERT,
                SurveyQuestion::TYPE_LIKERT_MATRIX,
            ],
        ]);
    }

    public function storePage(Survey $survey, Request $request, CreateSurveyPageAction $createPage): RedirectResponse
    {
        Gate::authorize('update', $survey);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $createPage->handle($request->user(), $survey, $data, $request);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'survey-page-created');
    }

    public function updateIntro(Survey $survey, Request $request, UpdateSurveyIntroAction $updateIntro): RedirectResponse
    {
        Gate::authorize('update', $survey);

        $removeIntroImage = $request->boolean('remove_intro_image');

        $data = $request->validate([
            'intro_title' => ['nullable', 'string', 'max:255'],
            'intro_text' => ['nullable', 'string', 'max:10000'],
            'estimated_duration' => ['nullable', 'string', 'max:100'],
            'privacy_statement' => ['nullable', 'string', 'max:10000'],
            'respondent_instruction' => ['nullable', 'string', 'max:10000'],
            'consent_text' => ['nullable', 'string', 'max:10000'],
            'require_consent_before_start' => ['nullable', 'boolean'],
            'intro_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'intro_image_alt_text' => [
                Rule::requiredIf(fn (): bool => ! $removeIntroImage && ($request->hasFile('intro_image') || filled($survey->intro_image_path))),
                'nullable',
                'string',
                'max:255',
            ],
            'intro_image_caption' => ['nullable', 'string', 'max:500'],
            'intro_image_source_note' => ['nullable', 'string', 'max:255'],
            'remove_intro_image' => ['nullable', 'boolean'],
        ]);

        $updateIntro->handle($request->user(), $survey, $data, $request);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'survey-intro-updated');
    }

    public function updateInstrumentSummary(Survey $survey, Request $request): RedirectResponse
    {
        Gate::authorize('update', $survey);

        $data = $request->validate([
            'instrument_summary_override' => ['nullable', 'string', 'max:10000'],
            'summary_action' => ['required', Rule::in(['use_manual', 'clear_manual', 'generate'])],
        ]);

        if ($data['summary_action'] === 'clear_manual' || $data['summary_action'] === 'generate') {
            $survey->forceFill(['instrument_summary_override' => null])->save();
        }

        if ($data['summary_action'] === 'use_manual') {
            $survey->forceFill([
                'instrument_summary_override' => $data['instrument_summary_override'] ?? null,
            ])->save();
        }

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'survey-instrument-summary-updated');
    }

    public function previewBulkQuestions(Survey $survey, Request $request, SurveyBulkQuestionImportService $bulkImport): RedirectResponse
    {
        Gate::authorize('update', $survey);

        $data = $request->validate([
            'bulk_input' => ['required', 'string', 'max:50000'],
            'indicator_strategy' => ['required', Rule::in(['create', 'skip', 'cancel'])],
        ]);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->withInput($data)
            ->with('bulk_question_preview', $bulkImport->preview($survey, $data['bulk_input'], $data['indicator_strategy']));
    }

    public function importBulkQuestions(Survey $survey, Request $request, SurveyBulkQuestionImportService $bulkImport): RedirectResponse
    {
        Gate::authorize('update', $survey);

        $data = $request->validate([
            'bulk_input' => ['required', 'string', 'max:50000'],
            'indicator_strategy' => ['required', Rule::in(['create', 'skip', 'cancel'])],
        ]);

        $result = $bulkImport->import($request->user(), $survey, $data['bulk_input'], $data['indicator_strategy']);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'survey-bulk-questions-imported-'.$result['question_count']);
    }

    public function createPharmVrStudentNeedsTemplate(Survey $survey, Request $request, PharmVrStudentNeedsSurveyTemplateService $template): RedirectResponse
    {
        Gate::authorize('update', $survey);

        $result = $template->create($request->user(), $survey);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'survey-pharmvr-template-created-'.$result['questions']);
    }

    public function fillMissingPharmVrStudentNeedsTemplate(Survey $survey, Request $request, PharmVrStudentNeedsSurveyTemplateService $template): RedirectResponse
    {
        Gate::authorize('update', $survey);

        $result = $template->fillMissing($request->user(), $survey);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'survey-pharmvr-template-filled-missing-'.$result['questions']);
    }

    public function normalizePharmVrStudentNeedsTemplate(Survey $survey, Request $request, PharmVrStudentNeedsSurveyTemplateService $template): RedirectResponse
    {
        Gate::authorize('update', $survey);

        $result = $template->normalizeExisting($request->user(), $survey);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'survey-pharmvr-template-normalized-'.$result['questions']);
    }

    public function normalizeLecturerAnalysisInstrument(
        Survey $survey,
        Request $request,
        CreateLecturerNeedsAnalysisQuestionnaireAction $normalizeLecturer,
    ): RedirectResponse {
        Gate::authorize('update', $survey);
        abort_unless($survey->instrument_type === Survey::INSTRUMENT_ANALYSIS_LECTURER && $survey->parentSurvey, 404);

        $normalizeLecturer->handle($request->user(), $survey->parentSurvey, $request);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'lecturer-instrument-normalized');
    }

    public function normalizePractitionerInterviewInstrument(
        Survey $survey,
        Request $request,
        CreatePractitionerInterviewFormAction $normalizePractitioner,
    ): RedirectResponse {
        Gate::authorize('update', $survey);
        abort_unless($survey->instrument_type === Survey::INSTRUMENT_PRACTITIONER_INTERVIEW && $survey->parentSurvey, 404);

        $normalizePractitioner->handle($request->user(), $survey->parentSurvey, $request);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'practitioner-interview-normalized');
    }

    public function updatePage(Survey $survey, SurveyPage $page, Request $request, UpdateSurveyPageAction $updatePage): RedirectResponse
    {
        Gate::authorize('update', $survey);
        abort_unless($page->survey_id === $survey->getKey(), 404);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $updatePage->handle($request->user(), $page, $data, $request);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'survey-page-updated');
    }

    public function deletePage(Survey $survey, SurveyPage $page, Request $request, DeleteSurveyPageAction $deletePage): RedirectResponse
    {
        Gate::authorize('update', $survey);
        abort_unless($page->survey_id === $survey->getKey(), 404);

        $deletePage->handle($request->user(), $page, $request);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'survey-page-deleted');
    }

    public function storeQuestion(Survey $survey, Request $request, CreateSurveyQuestionAction $createQuestion): RedirectResponse
    {
        Gate::authorize('update', $survey);

        $data = $this->validateQuestion($request, $survey);

        $createQuestion->handle($request->user(), $survey, $data, $request);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'survey-question-created');
    }

    public function updateQuestion(Survey $survey, SurveyQuestion $question, Request $request, UpdateSurveyQuestionAction $updateQuestion): RedirectResponse
    {
        Gate::authorize('update', $survey);
        abort_unless($question->survey_id === $survey->getKey(), 404);

        $data = $this->validateQuestion($request, $survey, $question);

        $updateQuestion->handle($request->user(), $question, $data, $request);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'survey-question-updated');
    }

    public function deleteQuestion(Survey $survey, SurveyQuestion $question, Request $request, DeleteSurveyQuestionAction $deleteQuestion): RedirectResponse
    {
        Gate::authorize('update', $survey);
        abort_unless($question->survey_id === $survey->getKey(), 404);

        $deleteQuestion->handle($request->user(), $question, $request);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'survey-question-deleted');
    }

    public function duplicateQuestion(Survey $survey, SurveyQuestion $question, Request $request, DuplicateSurveyQuestionAction $duplicateQuestion): RedirectResponse
    {
        Gate::authorize('update', $survey);
        abort_unless($question->survey_id === $survey->getKey(), 404);

        $duplicateQuestion->handle($request->user(), $question, $request);

        return redirect()
            ->route('admin.surveys.builder.index', ['survey' => $survey])
            ->with('status', 'survey-question-duplicated');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateQuestion(Request $request, Survey $survey, ?SurveyQuestion $question = null): array
    {
        $this->mergeStructuredQuestionOptions($request);

        return $request->validate([
            'page_id' => [
                'nullable',
                'string',
                Rule::exists('survey_pages', 'id')->where('survey_id', $survey->getKey()),
            ],
            'question_key' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'type' => ['required', Rule::in(config('researchhub_surveys.question_types', []))],
            'label' => ['required', 'string', 'max:5000'],
            'help_text' => ['nullable', 'string', 'max:5000'],
            'options_json' => ['nullable', 'string', 'max:20000'],
            'settings_json' => ['nullable', 'string', 'max:20000'],
            'max_selections' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    private function mergeStructuredQuestionOptions(Request $request): void
    {
        $type = (string) $request->input('type');

        if (in_array($type, [SurveyQuestion::TYPE_SINGLE_CHOICE, SurveyQuestion::TYPE_MULTIPLE_CHOICE], true)) {
            $choices = $this->cleanList($request->input('choice_options', []));

            if ($choices !== []) {
                $request->merge([
                    'options_json' => json_encode(['choices' => $choices], JSON_THROW_ON_ERROR),
                ]);
            }
        }

        if ($type === SurveyQuestion::TYPE_MULTIPLE_CHOICE) {
            $settings = $this->jsonArray($request->input('settings_json'));
            $maxSelections = $request->input('max_selections');

            if (filled($maxSelections)) {
                $settings['max_selections'] = (int) $maxSelections;
            } else {
                unset($settings['max_selections']);
            }

            $request->merge([
                'settings_json' => $settings === [] ? null : json_encode($settings, JSON_THROW_ON_ERROR),
            ]);
        }

        if ($type === SurveyQuestion::TYPE_LIKERT) {
            $scale = $this->cleanList($request->input('likert_scale', []));

            if ($scale !== []) {
                $request->merge([
                    'settings_json' => json_encode(['scale' => $scale], JSON_THROW_ON_ERROR),
                ]);
            }
        }

        if ($type === SurveyQuestion::TYPE_LIKERT_MATRIX) {
            $rows = $this->cleanList($request->input('matrix_rows', []));
            $columns = $this->matrixColumns(
                $request->input('matrix_column_values', []),
                $request->input('matrix_column_labels', []),
            );

            if ($rows !== [] || $columns !== []) {
                $request->merge([
                    'options_json' => json_encode([
                        'rows' => $rows,
                        'columns' => $columns,
                    ], JSON_THROW_ON_ERROR),
                ]);
            }
        }
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function matrixColumns(mixed $values, mixed $labels): array
    {
        if (! is_array($values) || ! is_array($labels)) {
            return [];
        }

        $columns = [];
        $max = max(count($values), count($labels));

        for ($index = 0; $index < $max; $index++) {
            $value = trim((string) ($values[$index] ?? ''));
            $label = trim((string) ($labels[$index] ?? ''));

            if ($value === '' && $label === '') {
                continue;
            }

            $columns[] = [
                'value' => $value !== '' ? $value : (string) ($index + 1),
                'label' => $label,
            ];
        }

        return $columns;
    }

    /**
     * @return array<int, string>
     */
    private function cleanList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $value): string => trim((string) $value),
            $values,
        ), fn (string $value): bool => $value !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonArray(mixed $json): array
    {
        if (blank($json)) {
            return [];
        }

        try {
            $decoded = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];
    }

    private function showsStudentTemplateActions(Survey $survey): bool
    {
        return in_array($survey->instrument_type, [null, Survey::INSTRUMENT_OTHER, Survey::INSTRUMENT_ANALYSIS_STUDENT], true);
    }

    private function templateActionScope(Survey $survey): string
    {
        return match ($survey->instrument_type) {
            Survey::INSTRUMENT_ANALYSIS_LECTURER => 'lecturer',
            Survey::INSTRUMENT_PRACTITIONER_INTERVIEW => 'practitioner',
            default => $this->showsStudentTemplateActions($survey) ? 'student' : 'generic',
        };
    }
}
