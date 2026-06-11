<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyIndicator;
use App\Models\SurveyQuestion;
use App\Models\SurveyScale;
use App\Modules\Surveys\Actions\CreateSurveyIndicatorAction;
use App\Modules\Surveys\Actions\CreateSurveyScaleAction;
use App\Modules\Surveys\Actions\DeleteSurveyIndicatorAction;
use App\Modules\Surveys\Actions\DeleteSurveyScaleAction;
use App\Modules\Surveys\Actions\UpdateSurveyIndicatorAction;
use App\Modules\Surveys\Actions\UpdateSurveyQuestionScoringAction;
use App\Modules\Surveys\Actions\UpdateSurveyScaleAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSurveyScoringController extends Controller
{
    public function index(Survey $survey): View
    {
        Gate::authorize('manageScoring', $survey);

        $survey->load([
            'project',
            'scales.indicators',
            'indicators.scale',
            'questions.scoring.indicator',
        ])->loadCount('responses');

        return view('surveys.admin.scoring.index', [
            'survey' => $survey,
            'hasResponses' => $survey->responses_count > 0,
            'supportedTypes' => ['likert', 'single_choice', 'number', 'consent'],
        ]);
    }

    public function storeScale(Survey $survey, Request $request, CreateSurveyScaleAction $createScale): RedirectResponse
    {
        $createScale->handle($request->user(), $survey, $this->scaleData($request), $request);

        return redirect()->route('admin.surveys.scoring.index', ['survey' => $survey])->with('status', 'survey-scoring-scale-created');
    }

    public function updateScale(Survey $survey, SurveyScale $scale, Request $request, UpdateSurveyScaleAction $updateScale): RedirectResponse
    {
        abort_unless($scale->survey_id === $survey->getKey(), 404);
        $updateScale->handle($request->user(), $scale, $this->scaleData($request), $request);

        return redirect()->route('admin.surveys.scoring.index', ['survey' => $survey])->with('status', 'survey-scoring-scale-updated');
    }

    public function deleteScale(Survey $survey, SurveyScale $scale, Request $request, DeleteSurveyScaleAction $deleteScale): RedirectResponse
    {
        abort_unless($scale->survey_id === $survey->getKey(), 404);
        $deleteScale->handle($request->user(), $scale, $request);

        return redirect()->route('admin.surveys.scoring.index', ['survey' => $survey])->with('status', 'survey-scoring-scale-deleted');
    }

    public function storeIndicator(Survey $survey, Request $request, CreateSurveyIndicatorAction $createIndicator): RedirectResponse
    {
        $createIndicator->handle($request->user(), $survey, $this->indicatorData($request, $survey), $request);

        return redirect()->route('admin.surveys.scoring.index', ['survey' => $survey])->with('status', 'survey-scoring-indicator-created');
    }

    public function updateIndicator(Survey $survey, SurveyIndicator $indicator, Request $request, UpdateSurveyIndicatorAction $updateIndicator): RedirectResponse
    {
        abort_unless($indicator->survey_id === $survey->getKey(), 404);
        $updateIndicator->handle($request->user(), $indicator, $this->indicatorData($request, $survey), $request);

        return redirect()->route('admin.surveys.scoring.index', ['survey' => $survey])->with('status', 'survey-scoring-indicator-updated');
    }

    public function deleteIndicator(Survey $survey, SurveyIndicator $indicator, Request $request, DeleteSurveyIndicatorAction $deleteIndicator): RedirectResponse
    {
        abort_unless($indicator->survey_id === $survey->getKey(), 404);
        $deleteIndicator->handle($request->user(), $indicator, $request);

        return redirect()->route('admin.surveys.scoring.index', ['survey' => $survey])->with('status', 'survey-scoring-indicator-deleted');
    }

    public function updateQuestionScoring(Survey $survey, SurveyQuestion $question, Request $request, UpdateSurveyQuestionScoringAction $updateScoring): RedirectResponse
    {
        abort_unless($question->survey_id === $survey->getKey(), 404);
        $updateScoring->handle($request->user(), $question, $this->questionScoringData($request, $survey), $request);

        return redirect()->route('admin.surveys.scoring.index', ['survey' => $survey])->with('status', 'survey-question-scoring-updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function scaleData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function indicatorData(Request $request, Survey $survey): array
    {
        $data = $request->validate([
            'survey_scale_id' => [
                'nullable',
                'string',
                Rule::exists('survey_scales', 'id')->where('survey_id', $survey->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'interpretation_rules_json' => ['nullable', 'string', 'max:20000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $data['interpretation_rules'] = $this->jsonArray($data['interpretation_rules_json'] ?? null, 'interpretation_rules_json');
        unset($data['interpretation_rules_json']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function questionScoringData(Request $request, Survey $survey): array
    {
        $data = $request->validate([
            'survey_indicator_id' => [
                'nullable',
                'string',
                Rule::exists('survey_indicators', 'id')->where('survey_id', $survey->getKey()),
            ],
            'is_scored' => ['nullable', 'boolean'],
            'score_min' => ['nullable', 'numeric'],
            'score_max' => ['nullable', 'numeric', 'gte:score_min'],
            'weight' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'is_reverse_scored' => ['nullable', 'boolean'],
            'settings_json' => ['nullable', 'string', 'max:20000'],
        ]);

        $data['settings'] = $this->jsonArray($data['settings_json'] ?? null, 'settings_json');
        unset($data['settings_json']);

        return $data;
    }

    /**
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function jsonArray(?string $json, string $field): ?array
    {
        if (blank($json)) {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => 'JSON must be a valid object or array.',
            ]);
        }

        return $decoded;
    }
}
