<?php

namespace App\Modules\Surveys\Services;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use Illuminate\Support\Arr;

class SurveyResponseTerminationService
{
    /** @param array<string, mixed> $answers */
    public function shouldTerminate(Survey $survey, array $answers): bool
    {
        $survey->loadMissing('questions');

        return $survey->questions->contains(function (SurveyQuestion $question) use ($answers): bool {
            $terminateOn = Arr::get($question->settings ?? [], 'terminate_on');
            $answer = $answers[$question->question_key] ?? null;

            if ($question->type !== SurveyQuestion::TYPE_SINGLE_CHOICE || ! is_scalar($terminateOn) || ! is_scalar($answer)) {
                return false;
            }

            $choices = Arr::get($question->options ?? [], 'choices', $question->options ?? []);
            $allowed = collect(is_array($choices) ? $choices : [])->map(fn (mixed $choice): string => is_array($choice)
                ? (string) ($choice['value'] ?? $choice['label'] ?? '')
                : (string) $choice);

            return $allowed->contains((string) $terminateOn) && hash_equals((string) $terminateOn, (string) $answer);
        });
    }
}
