<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Modules\Surveys\Actions\SubmitSurveyResponseAction;
use App\Modules\Surveys\DTOs\SurveyResponseData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicSurveyController extends Controller
{
    public function show(Survey $survey): View
    {
        if (! $survey->canReceiveResponses()) {
            return view('surveys.unavailable');
        }

        $survey->load([
            'pages.questions',
            'questions' => fn ($query) => $query->whereNull('page_id')->orderBy('sort_order'),
        ]);

        return view('surveys.show', [
            'survey' => $survey,
        ]);
    }

    public function store(Survey $survey, Request $request, SubmitSurveyResponseAction $submitSurveyResponse): View|RedirectResponse|Response
    {
        try {
            $submitSurveyResponse->handle($survey, new SurveyResponseData(
                answers: $request->input('answers', []),
                identity: $request->input('identity', []),
            ), $request);
        } catch (ValidationException $exception) {
            if (array_key_exists('survey', $exception->errors())) {
                return response()
                    ->view('surveys.unavailable', [], 403);
            }

            throw $exception;
        }

        return view('surveys.thank-you');
    }
}
