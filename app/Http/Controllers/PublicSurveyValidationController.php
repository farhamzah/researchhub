<?php

namespace App\Http\Controllers;

use App\Models\SurveyQuestion;
use App\Models\SurveyValidationScore;
use App\Modules\Validation\Actions\SubmitSurveyValidationScoresAction;
use App\Modules\Validation\Services\SurveyValidationTokenResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicSurveyValidationController extends Controller
{
    public function show(string $token, Request $request, SurveyValidationTokenResolver $resolver): View|Response
    {
        $assignment = $resolver->resolve($token, $request, true);

        if (! $assignment) {
            abort(404);
        }

        if ($assignment->isSubmitted()) {
            return view('survey-validation.thank-you', ['assignment' => $assignment]);
        }

        if (! $assignment->isAccessible()) {
            return response()->view('survey-validation.unavailable', [
                'assignment' => $assignment,
            ], 403);
        }

        $survey = $assignment->round->survey;
        $questions = $survey->questions()
            ->with('scoring.indicator')
            ->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)
            ->orderBy('sort_order')
            ->get();

        return view('survey-validation.show', [
            'assignment' => $assignment,
            'round' => $assignment->round,
            'survey' => $survey,
            'project' => $survey->project,
            'validator' => $assignment->validator,
            'questions' => $questions,
            'token' => $token,
            'recommendations' => SurveyValidationScore::RECOMMENDATION_LABELS,
        ]);
    }

    public function store(
        string $token,
        Request $request,
        SurveyValidationTokenResolver $resolver,
        SubmitSurveyValidationScoresAction $submitScores,
    ): View|RedirectResponse {
        $assignment = $resolver->resolve($token, $request, false);

        if (! $assignment) {
            abort(404);
        }

        if ($assignment->isSubmitted()) {
            return view('survey-validation.thank-you', ['assignment' => $assignment]);
        }

        if (! $assignment->isAccessible()) {
            return response()->view('survey-validation.unavailable', [
                'assignment' => $assignment,
            ], 403);
        }

        $submitScores->handle($assignment, $request->input('scores', []), $request);

        return redirect()->route('validation.survey.show', ['token' => $token]);
    }
}
