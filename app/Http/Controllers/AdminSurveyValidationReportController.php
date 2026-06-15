<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyValidationRound;
use App\Modules\Validation\Services\SurveyValidationResultService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminSurveyValidationReportController extends Controller
{
    public function __invoke(
        Survey $survey,
        SurveyValidationRound $round,
        SurveyValidationResultService $resultService,
    ): View {
        abort_unless($round->survey_id === $survey->getKey(), 404);
        Gate::authorize('manageValidation', $survey);

        return view('surveys.admin.validation.printable-report', [
            'survey' => $survey->load('project'),
            'round' => $round,
            'result' => $resultService->analyze($round),
            'generatedAt' => now(),
        ]);
    }
}
