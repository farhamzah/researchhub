<?php

namespace App\Http\Controllers;

use App\Models\AnalysisResult;
use App\Models\AnalysisSynthesisItem;
use App\Models\Survey;
use App\Modules\Analysis\Actions\RunSurveyDescriptiveAnalysisAction;
use App\Modules\Analysis\Services\AddieAnalysisDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSurveyAnalysisController extends Controller
{
    public function index(Survey $survey, Request $request, AddieAnalysisDashboardService $dashboardService): View
    {
        Gate::authorize('runAnalysis', $survey);

        $survey->load(['project', 'questions'])->loadCount('responses');
        $results = $survey->analysisResults()
            ->with(['job', 'narratives'])
            ->latest()
            ->get();

        return view('analysis.admin.show', [
            'survey' => $survey,
            'results' => $results,
            'result' => $results->first(),
            'dashboard' => $dashboardService->build($survey),
            'synthesisOptions' => $this->synthesisOptions(),
        ]);
    }

    public function run(Survey $survey, Request $request, RunSurveyDescriptiveAnalysisAction $runAnalysis): RedirectResponse
    {
        $result = $runAnalysis->handle($request->user(), $survey, $request);

        return redirect()
            ->route('admin.analysis.results.show', ['analysisResult' => $result])
            ->with('status', 'analysis-completed');
    }

    public function show(AnalysisResult $analysisResult): View
    {
        Gate::authorize('view', $analysisResult);

        $analysisResult->load(['job', 'project', 'survey', 'tables', 'narratives']);

        return view('analysis.admin.show', [
            'survey' => $analysisResult->survey,
            'results' => $analysisResult->survey?->analysisResults()
                ->with(['job', 'narratives'])
                ->latest()
                ->get() ?? collect(),
            'result' => $analysisResult,
            'dashboard' => $analysisResult->survey ? app(AddieAnalysisDashboardService::class)->build($analysisResult->survey) : null,
            'synthesisOptions' => $this->synthesisOptions(),
        ]);
    }

    public function storeSynthesisItem(Survey $survey, Request $request): RedirectResponse
    {
        Gate::authorize('runAnalysis', $survey);

        $survey->synthesisItems()->create([
            ...$this->synthesisItemData($request),
            'project_id' => $survey->project_id,
        ]);

        return redirect()
            ->route('admin.surveys.analysis.index', ['survey' => $survey])
            ->with('status', 'analysis-synthesis-item-created');
    }

    public function updateSynthesisItem(Survey $survey, AnalysisSynthesisItem $synthesisItem, Request $request): RedirectResponse
    {
        abort_unless($synthesisItem->survey_id === $survey->getKey(), 404);
        Gate::authorize('runAnalysis', $survey);

        $synthesisItem->update($this->synthesisItemData($request));

        return redirect()
            ->route('admin.surveys.analysis.index', ['survey' => $survey])
            ->with('status', 'analysis-synthesis-item-updated');
    }

    public function deleteSynthesisItem(Survey $survey, AnalysisSynthesisItem $synthesisItem): RedirectResponse
    {
        abort_unless($synthesisItem->survey_id === $survey->getKey(), 404);
        Gate::authorize('runAnalysis', $survey);

        $synthesisItem->delete();

        return redirect()
            ->route('admin.surveys.analysis.index', ['survey' => $survey])
            ->with('status', 'analysis-synthesis-item-deleted');
    }

    public function generateSynthesis(Survey $survey, AddieAnalysisDashboardService $dashboardService): RedirectResponse
    {
        Gate::authorize('runAnalysis', $survey);

        $result = $dashboardService->generateDraftSynthesis($survey);

        return redirect()
            ->route('admin.surveys.analysis.index', ['survey' => $survey])
            ->with('status', 'analysis-synthesis-generated-'.$result['created'].'-created-'.$result['skipped'].'-skipped');
    }

    public function report(Survey $survey, AddieAnalysisDashboardService $dashboardService): View
    {
        Gate::authorize('runAnalysis', $survey);

        return view('analysis.admin.report', [
            'survey' => $survey,
            'dashboard' => $dashboardService->build($survey),
            'generatedAt' => now(),
            'synthesisOptions' => $this->synthesisOptions(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function synthesisItemData(Request $request): array
    {
        return $request->validate([
            'source_type' => ['required', 'string', Rule::in(AnalysisSynthesisItem::SOURCES)],
            'source_label' => ['nullable', 'string', 'max:255'],
            'theme' => ['required', 'string', Rule::in(AnalysisSynthesisItem::THEMES)],
            'finding' => ['required', 'string', 'max:10000'],
            'evidence_summary' => ['nullable', 'string', 'max:10000'],
            'evidence_metric' => ['nullable', 'string', 'max:255'],
            'priority_level' => ['required', 'string', Rule::in(AnalysisSynthesisItem::PRIORITIES)],
            'design_implication' => ['nullable', 'string', 'max:10000'],
            'development_decision' => ['nullable', 'string', 'max:10000'],
            'mapped_module' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(AnalysisSynthesisItem::STATUSES)],
            'researcher_note' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    /**
     * @return array<string, array<string, string>|array<int, string>>
     */
    private function synthesisOptions(): array
    {
        return [
            'sources' => AnalysisSynthesisItem::SOURCE_LABELS,
            'themes' => AnalysisSynthesisItem::THEME_LABELS,
            'priorities' => AnalysisSynthesisItem::PRIORITY_LABELS,
            'statuses' => AnalysisSynthesisItem::STATUS_LABELS,
            'modules' => AnalysisSynthesisItem::MODULE_OPTIONS,
        ];
    }
}
