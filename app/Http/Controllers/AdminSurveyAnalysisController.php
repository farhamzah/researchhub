<?php

namespace App\Http\Controllers;

use App\Models\AnalysisResult;
use App\Models\Survey;
use App\Modules\Analysis\Actions\RunSurveyDescriptiveAnalysisAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminSurveyAnalysisController extends Controller
{
    public function index(Survey $survey): View
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
        ]);
    }
}
