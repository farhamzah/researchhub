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
use Illuminate\Support\Facades\DB;
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
            'supportedTypes' => ['likert', 'single_choice', 'multiple_choice', 'number'],
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

    public function convertMatrixQuestion(Survey $survey, SurveyQuestion $question, Request $request): RedirectResponse
    {
        Gate::authorize('manageScoring', $survey);
        abort_unless($question->survey_id === $survey->getKey(), 404);
        abort_unless($question->type === SurveyQuestion::TYPE_LIKERT_MATRIX, 404);

        if ($survey->responses()->where('status', 'submitted')->exists()) {
            throw ValidationException::withMessages([
                'scoring' => 'Matrix conversion is locked after submitted responses exist.',
            ]);
        }

        $created = DB::transaction(function () use ($survey, $question): int {
            $rows = collect($question->options['rows'] ?? [])->map(fn (mixed $row): string => trim((string) $row))->filter()->values();
            $columns = collect($question->options['columns'] ?? $question->settings['scale'] ?? [1, 2, 3, 4, 5]);
            $scale = $columns
                ->map(fn (mixed $column): string => is_array($column) ? (string) ($column['value'] ?? $column['label'] ?? '') : (string) $column)
                ->filter()
                ->values()
                ->all();
            $sortOrder = (int) $question->sort_order;
            $created = 0;

            foreach ($rows as $index => $row) {
                $key = $question->question_key.'_row_'.($index + 1);
                if ($survey->questions()->where('question_key', $key)->exists()) {
                    throw ValidationException::withMessages([
                        'scoring' => "Cannot convert matrix because generated key {$key} already exists.",
                    ]);
                }

                $newQuestion = $survey->questions()->create([
                    'page_id' => $question->page_id,
                    'question_key' => $key,
                    'type' => SurveyQuestion::TYPE_LIKERT,
                    'label' => $row,
                    'help_text' => $question->help_text,
                    'settings' => ['scale' => $scale],
                    'is_required' => $question->is_required,
                    'sort_order' => $sortOrder + $index + 1,
                ]);

                if ($question->scoring?->indicator) {
                    $newQuestion->scoring()->create([
                        'survey_id' => $survey->id,
                        'survey_indicator_id' => $question->scoring->indicator->id,
                        'is_scored' => true,
                        'score_min' => $question->scoring->score_min ?? min($scale),
                        'score_max' => $question->scoring->score_max ?? max($scale),
                        'weight' => $question->scoring->weight ?? 1,
                        'is_reverse_scored' => false,
                    ]);
                }

                $created++;
            }

            $question->scoring()->updateOrCreate(
                ['survey_question_id' => $question->id],
                [
                    'survey_id' => $survey->id,
                    'is_scored' => false,
                    'settings' => ['converted_to_likert_rows' => true],
                ],
            );

            return $created;
        });

        return redirect()
            ->route('admin.surveys.scoring.index', ['survey' => $survey])
            ->with('status', 'survey-matrix-converted-'.$created);
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
