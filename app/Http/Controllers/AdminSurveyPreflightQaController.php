<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Modules\Analysis\Services\AnalysisPreflightQaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSurveyPreflightQaController extends Controller
{
    public function index(Survey $survey, Request $request, AnalysisPreflightQaService $preflight): View
    {
        Gate::authorize('runAnalysis', $survey);

        return view('surveys.admin.preflight.index', [
            'survey' => $survey,
            'qa' => $preflight->build($survey, $request->user()),
        ]);
    }

    public function fixStudentOpenQuestions(
        Survey $survey,
        Request $request,
        AnalysisPreflightQaService $preflight,
    ): RedirectResponse {
        Gate::authorize('runAnalysis', $survey);

        $result = $preflight->fixStudentSectionG($survey);

        return redirect()
            ->route('admin.surveys.preflight.index', ['survey' => $survey])
            ->with('status', 'student-section-g-fixed-'.$result['added'].'-added-'.$result['skipped'].'-skipped');
    }

    public function markReady(
        Survey $survey,
        Request $request,
        AnalysisPreflightQaService $preflight,
    ): RedirectResponse {
        Gate::authorize('runAnalysis', $survey);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $preflight->markReady($survey, $request->user(), $data['notes'] ?? null);

        return redirect()
            ->route('admin.surveys.preflight.index', ['survey' => $survey])
            ->with('status', 'preflight-marked-ready-to-send');
    }

    public function report(Survey $survey, Request $request, AnalysisPreflightQaService $preflight): View
    {
        Gate::authorize('runAnalysis', $survey);

        return view('surveys.admin.preflight.report', [
            'survey' => $survey,
            'qa' => $preflight->build($survey, $request->user()),
            'generatedAt' => now(),
        ]);
    }

    public function exportCsv(Survey $survey, Request $request, AnalysisPreflightQaService $preflight): StreamedResponse
    {
        Gate::authorize('runAnalysis', $survey);

        $qa = $preflight->build($survey, $request->user());
        $filename = 'preflight-qa-'.$survey->slug.'.csv';

        return response()->streamDownload(function () use ($qa): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'source_type',
                'check_key',
                'label',
                'severity',
                'status',
                'message',
                'recommendation',
            ]);

            foreach ($qa['checks'] as $check) {
                fputcsv($handle, [
                    $check['source_type'],
                    $check['check_key'],
                    $check['label'],
                    $check['severity'],
                    $check['status'],
                    $check['message'],
                    $check['recommendation'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
