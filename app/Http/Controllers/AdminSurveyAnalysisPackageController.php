<?php

namespace App\Http\Controllers;

use App\Models\AnalysisDocumentPackage;
use App\Models\Survey;
use App\Modules\Analysis\Services\AnalysisDocumentPackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSurveyAnalysisPackageController extends Controller
{
    public function index(Survey $survey, Request $request, AnalysisDocumentPackageService $packageService): View
    {
        Gate::authorize('runAnalysis', $survey);

        return view('surveys.admin.analysis-package.index', [
            'survey' => $survey,
            'packageData' => $packageService->build($survey, $request->user()),
        ]);
    }

    public function updateMetadata(
        Survey $survey,
        Request $request,
        AnalysisDocumentPackageService $packageService,
    ): RedirectResponse {
        Gate::authorize('runAnalysis', $survey);

        $data = $request->validate($this->metadataRules());
        $packageData = $packageService->build($survey, $request->user());
        /** @var AnalysisDocumentPackage $package */
        $package = $packageData['package'];
        $package->update($data);

        return redirect()
            ->route('admin.surveys.analysis-package.index', ['survey' => $survey])
            ->with('status', 'analysis-package-metadata-updated');
    }

    public function print(Survey $survey, Request $request, AnalysisDocumentPackageService $packageService): View
    {
        Gate::authorize('runAnalysis', $survey);

        return view('surveys.admin.analysis-package.print', [
            'survey' => $survey,
            'packageData' => $packageService->build($survey, $request->user()),
            'printMode' => true,
        ]);
    }

    public function exportHtml(Survey $survey, Request $request, AnalysisDocumentPackageService $packageService): Response
    {
        Gate::authorize('runAnalysis', $survey);

        $html = view('surveys.admin.analysis-package.print', [
            'survey' => $survey,
            'packageData' => $packageService->build($survey, $request->user()),
            'printMode' => true,
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="analysis-package-'.$survey->slug.'.html"',
        ]);
    }

    public function exportDoc(Survey $survey, Request $request, AnalysisDocumentPackageService $packageService): Response
    {
        Gate::authorize('runAnalysis', $survey);

        $html = view('surveys.admin.analysis-package.print', [
            'survey' => $survey,
            'packageData' => $packageService->build($survey, $request->user()),
            'printMode' => true,
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="analysis-package-'.$survey->slug.'.doc"',
        ]);
    }

    public function finalize(
        Survey $survey,
        Request $request,
        AnalysisDocumentPackageService $packageService,
    ): RedirectResponse {
        Gate::authorize('runAnalysis', $survey);

        $packageService->finalize($survey, $request->user());

        return redirect()
            ->route('admin.surveys.analysis-package.index', ['survey' => $survey])
            ->with('status', 'analysis-package-finalized');
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'document_code' => ['nullable', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:255'],
            'document_date' => ['nullable', 'date'],
            'researcher_name' => ['nullable', 'string', 'max:255'],
            'researcher_identifier' => ['nullable', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'study_program' => ['nullable', 'string', 'max:255'],
            'promoter_name' => ['nullable', 'string', 'max:255'],
            'co_promoter_names' => ['nullable', 'string', 'max:10000'],
            'stage' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(AnalysisDocumentPackage::STATUSES)],
            'purpose_text' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
