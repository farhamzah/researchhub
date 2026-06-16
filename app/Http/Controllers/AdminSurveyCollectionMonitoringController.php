<?php

namespace App\Http\Controllers;

use App\Models\AnalysisCollectionTarget;
use App\Models\Survey;
use App\Modules\Analysis\Services\AnalysisCollectionMonitoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSurveyCollectionMonitoringController extends Controller
{
    public function index(Survey $survey, Request $request, AnalysisCollectionMonitoringService $monitoring): View
    {
        Gate::authorize('runAnalysis', $survey);

        return view('surveys.admin.collection-monitoring.index', [
            'survey' => $survey,
            'monitoring' => $monitoring->build($survey, $request->user()),
        ]);
    }

    public function updateTarget(
        Survey $survey,
        AnalysisCollectionTarget $target,
        Request $request,
    ): RedirectResponse {
        abort_unless($target->survey_id === $survey->getKey(), 404);
        Gate::authorize('runAnalysis', $survey);

        $data = $request->validate([
            'minimum_count' => ['required', 'integer', 'min:0', 'max:100000'],
            'target_count' => ['required', 'integer', 'min:0', 'max:100000'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'status' => ['nullable', 'string', Rule::in(array_keys(AnalysisCollectionTarget::STATUS_LABELS))],
        ]);

        $target->update($data);

        return redirect()
            ->route('admin.surveys.collection-monitoring.index', ['survey' => $survey])
            ->with('status', 'analysis-collection-target-updated');
    }

    public function report(Survey $survey, Request $request, AnalysisCollectionMonitoringService $monitoring): View
    {
        Gate::authorize('runAnalysis', $survey);

        return view('surveys.admin.collection-monitoring.report', [
            'survey' => $survey,
            'monitoring' => $monitoring->build($survey, $request->user()),
            'generatedAt' => now(),
        ]);
    }

    public function exportCsv(Survey $survey, Request $request, AnalysisCollectionMonitoringService $monitoring): StreamedResponse
    {
        Gate::authorize('runAnalysis', $survey);

        $data = $monitoring->build($survey, $request->user());
        $filename = 'analysis-collection-monitoring-'.$survey->slug.'.csv';

        return response()->streamDownload(function () use ($data): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'source_type',
                'label',
                'current_count',
                'minimum_count',
                'target_count',
                'completion_rate',
                'due_date',
                'status',
                'notes',
            ]);

            foreach ($data['sources'] as $source) {
                fputcsv($handle, [
                    $source['source_type'],
                    $source['label'],
                    $source['current_count'],
                    $source['minimum_count'],
                    $source['target_count'],
                    $source['completion_rate'].'%',
                    $source['target']->due_date?->toDateString(),
                    $source['status_label'],
                    $source['target']->notes,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
