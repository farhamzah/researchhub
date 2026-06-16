<?php

namespace App\Http\Controllers;

use App\Models\AnalysisPilotRun;
use App\Models\Survey;
use App\Modules\Analysis\Services\AnalysisRespondentPackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminSurveyRespondentPackageController extends Controller
{
    public function index(Survey $survey, Request $request, AnalysisRespondentPackageService $package): View
    {
        Gate::authorize('runAnalysis', $survey);

        return view('surveys.admin.respondent-package.index', [
            'survey' => $survey,
            'package' => $package->build($survey, $request->user()),
        ]);
    }

    public function generatePilot(
        Survey $survey,
        string $audience,
        Request $request,
        AnalysisRespondentPackageService $package,
    ): RedirectResponse {
        Gate::authorize('runAnalysis', $survey);
        abort_unless(in_array($audience, AnalysisPilotRun::AUDIENCES, true), 404);

        $result = $package->generatePilotLink($survey, $audience, $request->user());

        return redirect()
            ->route('admin.surveys.respondent-package.index', ['survey' => $survey])
            ->with('generated_pilot_url', $result['url'])
            ->with('generated_pilot_run_id', $result['run']->getKey())
            ->with('status', 'pilot-link-generated');
    }

    public function revokePilot(
        Survey $survey,
        AnalysisPilotRun $pilotRun,
        AnalysisRespondentPackageService $package,
    ): RedirectResponse {
        Gate::authorize('runAnalysis', $survey);

        $package->revokePilotRun($survey, $pilotRun);

        return redirect()
            ->route('admin.surveys.respondent-package.index', ['survey' => $survey])
            ->with('status', 'pilot-link-revoked');
    }

    public function updateChecklist(
        Survey $survey,
        AnalysisPilotRun $pilotRun,
        Request $request,
        AnalysisRespondentPackageService $package,
    ): RedirectResponse {
        Gate::authorize('runAnalysis', $survey);

        $data = $request->validate([
            'intro_ok' => ['nullable', 'boolean'],
            'consent_ok' => ['nullable', 'boolean'],
            'questions_ok' => ['nullable', 'boolean'],
            'required_validation_ok' => ['nullable', 'boolean'],
            'submit_ok' => ['nullable', 'boolean'],
            'thank_you_ok' => ['nullable', 'boolean'],
            'excluded_from_analysis_ok' => ['nullable', 'boolean'],
            'mobile_view_ok' => ['nullable', 'boolean'],
            'desktop_view_ok' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $package->updateChecklist($survey, $pilotRun, $data);

        return redirect()
            ->route('admin.surveys.respondent-package.index', ['survey' => $survey])
            ->with('status', 'pilot-checklist-updated');
    }

    public function markFailed(
        Survey $survey,
        AnalysisPilotRun $pilotRun,
        Request $request,
        AnalysisRespondentPackageService $package,
    ): RedirectResponse {
        Gate::authorize('runAnalysis', $survey);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $package->markFailed($survey, $pilotRun, $data['notes'] ?? null);

        return redirect()
            ->route('admin.surveys.respondent-package.index', ['survey' => $survey])
            ->with('status', 'pilot-marked-failed');
    }

    public function clearAllTestResponses(Survey $survey, AnalysisRespondentPackageService $package): RedirectResponse
    {
        Gate::authorize('runAnalysis', $survey);

        $count = $package->clearTestResponses($survey);

        return redirect()
            ->route('admin.surveys.respondent-package.index', ['survey' => $survey])
            ->with('status', 'test-responses-cleared-'.$count);
    }

    public function clearTargetTestResponses(
        Survey $survey,
        Survey $targetSurvey,
        AnalysisRespondentPackageService $package,
    ): RedirectResponse {
        Gate::authorize('runAnalysis', $survey);

        $instruments = collect($package->analysisInstruments($survey))->filter()->map->getKey();
        abort_unless($instruments->contains($targetSurvey->getKey()), 404);

        $count = $package->clearTestResponses($survey, $targetSurvey);

        return redirect()
            ->route('admin.surveys.respondent-package.index', ['survey' => $survey])
            ->with('status', 'test-responses-cleared-'.$count);
    }
}
