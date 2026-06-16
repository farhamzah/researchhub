<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Modules\Surveys\Services\RespondentPrivacyService;
use App\Modules\Surveys\Services\SurveyResponseExportRowBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminSurveyResponseController extends Controller
{
    public function index(
        Survey $survey,
        Request $request,
        RespondentPrivacyService $privacyService,
        SurveyResponseExportRowBuilder $exportRowBuilder,
    ): View {
        Gate::authorize('view', $survey);

        $survey->load(['project', 'questions']);
        $responses = $survey->responses()
            ->official()
            ->with(['respondent', 'answers'])
            ->latest('submitted_at')
            ->paginate(25);

        return view('surveys.admin.responses.index', [
            'survey' => $survey,
            'responses' => $responses,
            'privacyService' => $privacyService,
            'exportPreviewRows' => collect($exportRowBuilder->buildForSurvey($survey, $request->user()))
                ->take(3),
        ]);
    }

    public function show(
        Survey $survey,
        SurveyResponse $response,
        Request $request,
        RespondentPrivacyService $privacyService,
        SurveyResponseExportRowBuilder $exportRowBuilder,
    ): View {
        Gate::authorize('view', $survey);
        abort_unless($response->survey_id === $survey->getKey(), 404);

        $survey->load(['project', 'questions']);
        $response->load(['respondent', 'answers.question']);

        return view('surveys.admin.responses.show', [
            'survey' => $survey,
            'response' => $response,
            'privacyService' => $privacyService,
            'exportRow' => $exportRowBuilder->build($response, $request->user()),
            'exportRowWithIdentity' => $exportRowBuilder->build($response, $request->user(), withIdentity: true),
        ]);
    }
}
