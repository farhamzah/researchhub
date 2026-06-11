<?php

namespace App\Modules\Surveys\Services;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SurveyAnswerValidationService
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function validate(Survey $survey, array $answers): array
    {
        $validated = [];

        foreach ($survey->questions as $question) {
            $key = $question->question_key;
            $value = $answers[$key] ?? null;

            if ($this->missing($value)) {
                if ($question->is_required) {
                    $this->fail($key, 'This question is required.');
                }

                continue;
            }

            $validated[$key] = $this->validateQuestion($question, $value);
        }

        return $validated;
    }

    private function validateQuestion(SurveyQuestion $question, mixed $value): mixed
    {
        return match ($question->type) {
            SurveyQuestion::TYPE_SHORT_TEXT => $this->validateText($question, $value, 500),
            SurveyQuestion::TYPE_LONG_TEXT => $this->validateText($question, $value, 5000),
            SurveyQuestion::TYPE_SINGLE_CHOICE => $this->validateSingleChoice($question, $value),
            SurveyQuestion::TYPE_MULTIPLE_CHOICE => $this->validateMultipleChoice($question, $value),
            SurveyQuestion::TYPE_LIKERT => $this->validateLikert($question, $value),
            SurveyQuestion::TYPE_LIKERT_MATRIX => $this->validateLikertMatrix($question, $value),
            SurveyQuestion::TYPE_NUMBER => $this->validateNumber($question, $value),
            SurveyQuestion::TYPE_DATE => $this->validateDate($question, $value),
            SurveyQuestion::TYPE_CONSENT => $this->validateConsent($question, $value),
            SurveyQuestion::TYPE_HIDDEN => $this->validateHidden($question, $value),
            default => $this->fail($question->question_key, 'Unsupported question type.'),
        };
    }

    private function validateText(SurveyQuestion $question, mixed $value, int $maxLength): string
    {
        if (! is_scalar($value)) {
            $this->fail($question->question_key, 'The answer must be text.');
        }

        $answer = trim((string) $value);

        if (mb_strlen($answer) > $maxLength) {
            $this->fail($question->question_key, "The answer may not be greater than {$maxLength} characters.");
        }

        return $answer;
    }

    private function validateSingleChoice(SurveyQuestion $question, mixed $value): string
    {
        if (! is_scalar($value)) {
            $this->fail($question->question_key, 'Choose one option.');
        }

        $answer = (string) $value;

        if (! in_array($answer, $this->allowedOptionValues($question), true)) {
            $this->fail($question->question_key, 'The selected option is invalid.');
        }

        return $answer;
    }

    private function validateMultipleChoice(SurveyQuestion $question, mixed $value): array
    {
        if (! is_array($value)) {
            $this->fail($question->question_key, 'Choose one or more options.');
        }

        $allowed = $this->allowedOptionValues($question);
        $answers = array_values(array_unique(array_map('strval', $value)));

        foreach ($answers as $answer) {
            if (! in_array($answer, $allowed, true)) {
                $this->fail($question->question_key, 'One or more selected options are invalid.');
            }
        }

        return $answers;
    }

    private function validateLikert(SurveyQuestion $question, mixed $value): int|string
    {
        if (! is_scalar($value)) {
            $this->fail($question->question_key, 'Choose one scale value.');
        }

        $answer = (string) $value;
        $allowed = array_map('strval', $this->likertScale($question));

        if (! in_array($answer, $allowed, true)) {
            $this->fail($question->question_key, 'The selected scale value is invalid.');
        }

        return is_numeric($answer) ? (int) $answer : $answer;
    }

    private function validateLikertMatrix(SurveyQuestion $question, mixed $value): array
    {
        if (! is_array($value)) {
            $this->fail($question->question_key, 'Complete the matrix answers.');
        }

        $rows = array_map('strval', Arr::get($question->options ?? [], 'rows', []));
        $columns = array_map('strval', Arr::get($question->options ?? [], 'columns', $this->likertScale($question)));
        $validated = [];

        foreach ($rows as $row) {
            $cell = $value[$row] ?? null;

            if ($this->missing($cell)) {
                if ($question->is_required) {
                    $this->fail($question->question_key, 'Complete all required matrix rows.');
                }

                continue;
            }

            $cell = (string) $cell;

            if (! in_array($cell, $columns, true)) {
                $this->fail($question->question_key, 'A matrix answer contains an invalid column.');
            }

            $validated[$row] = $cell;
        }

        return $validated;
    }

    private function validateNumber(SurveyQuestion $question, mixed $value): int|float
    {
        if (! is_numeric($value)) {
            $this->fail($question->question_key, 'The answer must be a number.');
        }

        return str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }

    private function validateDate(SurveyQuestion $question, mixed $value): string
    {
        if (! is_scalar($value)) {
            $this->fail($question->question_key, 'The answer must be a date.');
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            $this->fail($question->question_key, 'The answer must be a valid date.');
        }
    }

    private function validateConsent(SurveyQuestion $question, mixed $value): bool
    {
        $accepted = in_array($value, [true, 1, '1', 'yes', 'on', 'accepted'], true);

        if ($question->is_required && ! $accepted) {
            $this->fail($question->question_key, 'Consent is required.');
        }

        return $accepted;
    }

    private function validateHidden(SurveyQuestion $question, mixed $value): string
    {
        if (in_array($question->question_key, config('researchhub_surveys.reserved_hidden_keys', []), true)) {
            $this->fail($question->question_key, 'This hidden field is reserved.');
        }

        if (! is_scalar($value)) {
            $this->fail($question->question_key, 'The hidden answer must be scalar.');
        }

        return trim((string) $value);
    }

    /**
     * @return array<int, string>
     */
    private function allowedOptionValues(SurveyQuestion $question): array
    {
        $options = $question->options ?? [];

        if (array_is_list($options)) {
            return array_map(fn (mixed $option): string => is_array($option)
                ? (string) ($option['value'] ?? $option['label'] ?? '')
                : (string) $option, $options);
        }

        return array_map(fn (mixed $option): string => is_array($option)
            ? (string) ($option['value'] ?? $option['label'] ?? '')
            : (string) $option, Arr::get($options, 'choices', Arr::get($options, 'options', [])));
    }

    /**
     * @return array<int, int|string>
     */
    private function likertScale(SurveyQuestion $question): array
    {
        return Arr::get($question->settings ?? [], 'scale')
            ?? Arr::get($question->options ?? [], 'scale')
            ?? config('researchhub_surveys.default_likert_scale', [1, 2, 3, 4, 5]);
    }

    private function missing(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([
            "answers.{$key}" => $message,
        ]);
    }
}
