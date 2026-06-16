<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyReadabilityRound;
use App\Modules\Surveys\Services\SurveyReadabilityResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminSurveyReadabilityReportController extends Controller
{
    public function latest(Survey $survey, SurveyReadabilityResultService $resultService): View|RedirectResponse
    {
        Gate::authorize('manageValidation', $survey);

        $round = $survey->readabilityRounds()
            ->latest()
            ->first();

        if (! $round) {
            return redirect()
                ->route('admin.surveys.readability.index', ['survey' => $survey])
                ->with('status', 'survey-readability-report-needs-round');
        }

        return $this($survey, $round, $resultService);
    }

    public function __invoke(
        Survey $survey,
        SurveyReadabilityRound $round,
        SurveyReadabilityResultService $resultService,
    ): View {
        abort_unless($round->survey_id === $survey->getKey(), 404);
        Gate::authorize('manageValidation', $survey);

        return view('surveys.admin.readability.printable-report', [
            'survey' => $survey->load('project'),
            'round' => $round,
            'result' => $resultService->analyze($round),
            'generatedAt' => now(),
        ]);
    }
}
