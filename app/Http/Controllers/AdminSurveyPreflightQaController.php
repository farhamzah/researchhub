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
        $scope = $this->scope($request);

        return view('surveys.admin.preflight.index', [
            'survey' => $survey,
            'qa' => $preflight->build($survey, $request->user(), $scope),
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
            ->route('admin.surveys.preflight.index', [
                'survey' => $survey,
                'scope' => AnalysisPreflightQaService::SCOPE_STUDENT_QUESTIONNAIRE,
            ])
            ->with('status', 'student-questionnaire-structure-checked-'.$result['added'].'-added-'.$result['skipped'].'-approved-present');
    }

    public function removeObsoleteStudentKeys(
        Survey $survey,
        Request $request,
        AnalysisPreflightQaService $preflight,
    ): RedirectResponse {
        Gate::authorize('runAnalysis', $survey);

        $result = $preflight->removeObsoleteStudentKeys($survey);

        return redirect()
            ->route('admin.surveys.preflight.index', [
                'survey' => $survey,
                'scope' => AnalysisPreflightQaService::SCOPE_STUDENT_QUESTIONNAIRE,
            ])
            ->with('status', 'obsolete-student-keys-removed-'.$result['removed'].'-blocked-'.$result['blocked']);
    }

    public function markReady(
        Survey $survey,
        Request $request,
        AnalysisPreflightQaService $preflight,
    ): RedirectResponse {
        Gate::authorize('runAnalysis', $survey);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:10000'],
            'scope' => ['nullable', 'string'],
        ]);

        $scope = $this->scope($request);

        $preflight->markReady($survey, $request->user(), $data['notes'] ?? null, $scope);

        return redirect()
            ->route('admin.surveys.preflight.index', ['survey' => $survey, 'scope' => $scope])
            ->with('status', 'preflight-marked-ready-to-send');
    }

    public function report(Survey $survey, Request $request, AnalysisPreflightQaService $preflight): View
    {
        Gate::authorize('runAnalysis', $survey);
        $scope = $this->scope($request);

        return view('surveys.admin.preflight.report', [
            'survey' => $survey,
            'qa' => $preflight->build($survey, $request->user(), $scope),
            'generatedAt' => now(),
        ]);
    }

    public function exportCsv(Survey $survey, Request $request, AnalysisPreflightQaService $preflight): StreamedResponse
    {
        Gate::authorize('runAnalysis', $survey);

        $qa = $preflight->build($survey, $request->user(), $this->scope($request));
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

    private function scope(Request $request): string
    {
        return (string) $request->input('scope', AnalysisPreflightQaService::SCOPE_STUDENT_QUESTIONNAIRE);
    }
}
