<?php

namespace App\Http\Controllers;

use App\Models\SurveyQuestion;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyReadabilityQuestionFeedback;
use App\Models\SurveyReadabilityResponse;
use App\Modules\Surveys\Actions\SubmitSurveyReadabilityResponseAction;
use App\Modules\Surveys\Services\SurveyReadabilityTokenResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicSurveyReadabilityController extends Controller
{
    public function show(string $token, Request $request, SurveyReadabilityTokenResolver $resolver): View|Response
    {
        $participant = $resolver->resolve($token, $request, true);

        if (! $participant) {
            abort(404);
        }

        if ($participant->isSubmitted()) {
            return view('survey-readability.thank-you', ['participant' => $participant]);
        }

        if (! $participant->isAccessible()) {
            return response()->view('survey-readability.unavailable', [
                'participant' => $participant,
            ], 403);
        }

        $survey = $participant->round->survey;
        $questions = $survey->questions()
            ->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)
            ->orderBy('sort_order')
            ->get();

        return view('survey-readability.show', [
            'participant' => $participant,
            'round' => $participant->round,
            'survey' => $survey,
            'project' => $survey->project,
            'questions' => $questions,
            'token' => $token,
            'participantTypes' => SurveyReadabilityParticipant::TYPE_LABELS,
            'issueTypes' => SurveyReadabilityQuestionFeedback::ISSUE_LABELS,
            'finalDecisions' => SurveyReadabilityResponse::DECISION_LABELS,
        ]);
    }

    public function store(
        string $token,
        Request $request,
        SurveyReadabilityTokenResolver $resolver,
        SubmitSurveyReadabilityResponseAction $submitResponse,
    ): View|RedirectResponse|Response {
        $participant = $resolver->resolve($token, $request, false);

        if (! $participant) {
            abort(404);
        }

        if ($participant->isSubmitted()) {
            return view('survey-readability.thank-you', ['participant' => $participant]);
        }

        if (! $participant->isAccessible()) {
            return response()->view('survey-readability.unavailable', [
                'participant' => $participant,
            ], 403);
        }

        try {
            $submitResponse->handle($participant, $this->responseData($request, $participant), $request);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return redirect()->route('readability.survey.show', ['token' => $token]);
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(Request $request, SurveyReadabilityParticipant $participant): array
    {
        $questionIds = $participant->round->survey->questions()->pluck('id')->all();

        return $request->validate([
            'participant_name' => ['nullable', 'string', 'max:255'],
            'participant_type' => ['nullable', 'string', Rule::in(SurveyReadabilityParticipant::TYPES)],
            'institution' => ['nullable', 'string', 'max:255'],
            'overall_clarity_score' => ['required', 'integer', 'min:1', 'max:5'],
            'overall_length_score' => ['required', 'integer', 'min:1', 'max:5'],
            'terminology_clarity_score' => ['required', 'integer', 'min:1', 'max:5'],
            'answer_option_clarity_score' => ['required', 'integer', 'min:1', 'max:5'],
            'instruction_clarity_score' => ['required', 'integer', 'min:1', 'max:5'],
            'estimated_completion_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'confusing_items' => ['nullable', 'string', 'max:10000'],
            'general_comments' => ['nullable', 'string', 'max:10000'],
            'revision_suggestions' => ['nullable', 'string', 'max:10000'],
            'final_decision' => ['required', 'string', Rule::in(SurveyReadabilityResponse::DECISIONS)],
            'feedback' => ['nullable', 'array', 'max:20'],
            'feedback.*.survey_question_id' => ['nullable', 'string', Rule::in($questionIds)],
            'feedback.*.issue_type' => ['nullable', 'string', Rule::in(SurveyReadabilityQuestionFeedback::ISSUE_TYPES)],
            'feedback.*.comment' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
