<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyReadabilityRound;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\SurveyReadabilityResultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminSurveyReadabilityResultController extends Controller
{
    public function __invoke(
        Survey $survey,
        SurveyReadabilityRound $round,
        Request $request,
        SurveyReadabilityResultService $resultService,
        ActivityLogger $activityLogger,
    ): View {
        abort_unless($round->survey_id === $survey->getKey(), 404);
        Gate::authorize('manageValidation', $survey);

        $result = $resultService->analyze($round);

        $activityLogger->log('survey_readability_results.viewed', $request->user(), $survey->project, $round, [
            'survey_readability_round_id' => $round->getKey(),
            'survey_id' => $survey->getKey(),
            'research_project_id' => $survey->project_id,
            'submitted_count' => $result['summary']['submitted_count'],
            'question_count' => $result['summary']['question_count'],
        ], $request);

        return view('surveys.admin.readability.results', [
            'survey' => $survey,
            'round' => $result['round'],
            'result' => $result,
        ]);
    }
}
