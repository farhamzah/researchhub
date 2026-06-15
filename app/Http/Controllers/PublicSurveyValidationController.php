<?php

namespace App\Http\Controllers;

use App\Models\SurveyQuestion;
use App\Models\SurveyValidationRecommendation;
use App\Models\SurveyValidationScore;
use App\Modules\Validation\Actions\SubmitSurveyValidationScoresAction;
use App\Modules\Validation\Services\SurveyValidationTokenResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
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
            'feasibilityDecisions' => SurveyValidationRecommendation::DECISION_LABELS,
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

        try {
            $submitScores->handle(
                $assignment,
                $this->normalizePublicScores($assignment, $request->input('scores', [])),
                $request,
                $this->recommendationData($request),
            );
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->publicErrorMessages($assignment, $exception));
        }

        return redirect()->route('validation.survey.show', ['token' => $token]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePublicScores(mixed $assignment, mixed $scores): array
    {
        if (! is_array($scores)) {
            return [];
        }

        return $this->publicQuestions($assignment)
            ->mapWithKeys(function (SurveyQuestion $question, int $index) use ($scores): array {
                $legacyKey = $question->getKey();
                $publicKey = (string) $index;

                return [
                    $legacyKey => $scores[$legacyKey] ?? $scores[$publicKey] ?? $scores[$index] ?? [],
                ];
            })
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function publicErrorMessages(mixed $assignment, ValidationException $exception): array
    {
        $questionIndex = $this->publicQuestions($assignment)
            ->values()
            ->mapWithKeys(fn (SurveyQuestion $question, int $index): array => [$question->getKey() => (string) $index]);

        return collect($exception->errors())
            ->mapWithKeys(function (array $messages, string $field) use ($questionIndex): array {
                $publicField = preg_replace_callback(
                    '/^scores\.([^.]+)/',
                    fn (array $matches): string => 'scores.'.($questionIndex[$matches[1]] ?? $matches[1]),
                    $field,
                );

                return [$publicField ?: $field => $messages];
            })
            ->all();
    }

    /**
     * @return Collection<int, SurveyQuestion>
     */
    private function publicQuestions(mixed $assignment): Collection
    {
        return $assignment->round->survey->questions()
            ->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)
            ->orderBy('sort_order')
            ->get()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function recommendationData(Request $request): array
    {
        return $request->validate([
            'feasibility_decision' => ['nullable', 'string', 'in:'.implode(',', SurveyValidationRecommendation::DECISIONS)],
            'general_comments' => ['nullable', 'string', 'max:10000'],
            'revision_suggestions' => ['nullable', 'string', 'max:10000'],
        ]);
    }
}
