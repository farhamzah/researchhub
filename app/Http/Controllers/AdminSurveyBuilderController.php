<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Modules\Surveys\Actions\CreateSurveyPageAction;
use App\Modules\Surveys\Actions\CreateSurveyQuestionAction;
use App\Modules\Surveys\Actions\DeleteSurveyPageAction;
use App\Modules\Surveys\Actions\DeleteSurveyQuestionAction;
use App\Modules\Surveys\Actions\DuplicateSurveyQuestionAction;
use App\Modules\Surveys\Actions\UpdateSurveyPageAction;
use App\Modules\Surveys\Actions\UpdateSurveyQuestionAction;
use App\Modules\Surveys\Services\SurveyBuilderReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSurveyBuilderController extends Controller
{
    public function index(Survey $survey, SurveyBuilderReadinessService $readiness): View
    {
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
        ])->loadCount('responses');

        return view('surveys.admin.builder.index', [
            'survey' => $survey,
            'builderWizard' => $readiness->build($survey),
            'questionTypes' => config('researchhub_surveys.question_types', []),
            'hasResponses' => $survey->responses_count > 0,
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
            $columns = $this->cleanList($request->input('matrix_columns', []));

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
}
