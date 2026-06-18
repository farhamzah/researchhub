<?php

namespace App\Http\Controllers;

use App\Models\AnalysisPilotRun;
use App\Models\Survey;
use App\Modules\Analysis\Services\AnalysisRespondentPackageService;
use App\Modules\Surveys\Actions\SubmitSurveyResponseAction;
use App\Modules\Surveys\DTOs\SurveyResponseData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicSurveyController extends Controller
{
    public function show(Survey $survey, Request $request, AnalysisRespondentPackageService $respondentPackage): View|Response
    {
        $pilotToken = $request->query('pilot');
        $pilotRun = $respondentPackage->resolvePilotRun($survey, is_string($pilotToken) ? $pilotToken : null);

        if (filled($pilotToken) && ! $pilotRun) {
            return response()
                ->view('surveys.unavailable', [
                    'title' => 'Pilot link is no longer active.',
                    'message' => 'This pilot test link is invalid, expired, or revoked. Request a fresh pilot link from the researcher.',
                ], 403);
        }

        if (! $survey->canReceiveResponses() && ! $pilotRun) {
            return view('surveys.unavailable');
        }

        $survey->load([
            'pages.questions',
            'questions' => fn ($query) => $query->whereNull('page_id')->orderBy('sort_order'),
        ]);

        return view('surveys.show', [
            'survey' => $survey,
            'pilotRun' => $pilotRun,
            'pilotToken' => $pilotToken,
        ]);
    }

    public function store(
        Survey $survey,
        Request $request,
        SubmitSurveyResponseAction $submitSurveyResponse,
        AnalysisRespondentPackageService $respondentPackage,
    ): View|RedirectResponse|Response {
        $pilotToken = $request->input('pilot');
        $pilotRun = $respondentPackage->resolvePilotRun($survey, is_string($pilotToken) ? $pilotToken : null);

        if (filled($pilotToken) && ! $pilotRun) {
            return response()
                ->view('surveys.unavailable', [
                    'title' => 'Pilot link is no longer active.',
                    'message' => 'This pilot test link is invalid, expired, or revoked. It was not stored as real respondent data.',
                ], 403);
        }

        if (($survey->canReceiveResponses() || $pilotRun) && $survey->require_consent_before_start && $request->input('intro_consent') !== '1') {
            throw ValidationException::withMessages([
                'intro_consent' => 'Please confirm that you have read the survey explanation before continuing.',
            ]);
        }

        try {
            $submitSurveyResponse->handle($survey, new SurveyResponseData(
                answers: $request->input('answers', []),
                identity: $request->input('identity', []),
            ), $request, $pilotRun);
        } catch (ValidationException $exception) {
            if (array_key_exists('survey', $exception->errors())) {
                return response()
                    ->view('surveys.unavailable', [], 403);
            }

            throw $exception;
        }

        return view('surveys.thank-you', [
            'isPilot' => $pilotRun instanceof AnalysisPilotRun,
        ]);
    }
}
