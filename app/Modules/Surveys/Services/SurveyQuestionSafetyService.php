<?php

namespace App\Modules\Surveys\Services;

use App\Models\Survey;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use Illuminate\Validation\ValidationException;

class SurveyQuestionSafetyService
{
    public function hasResponses(Survey $survey): bool
    {
        return $survey->responses()->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function ensureQuestionUpdateIsSafe(SurveyQuestion $question, array $attributes): void
    {
        if (! $this->hasResponses($question->survey)) {
            return;
        }

        if (array_key_exists('question_key', $attributes) && $attributes['question_key'] !== $question->question_key) {
            throw ValidationException::withMessages([
                'question_key' => 'Question key cannot be changed after responses exist.',
            ]);
        }

        if (array_key_exists('type', $attributes) && $attributes['type'] !== $question->type) {
            throw ValidationException::withMessages([
                'type' => 'Question type cannot be changed after responses exist.',
            ]);
        }
    }

    public function ensureQuestionDeleteIsSafe(SurveyQuestion $question): void
    {
        if ($this->hasResponses($question->survey)) {
            throw ValidationException::withMessages([
                'question' => 'Questions cannot be deleted after responses exist.',
            ]);
        }
    }

    public function ensurePageDeleteIsSafe(SurveyPage $page): void
    {
        if ($this->hasResponses($page->survey)) {
            throw ValidationException::withMessages([
                'page' => 'Pages cannot be deleted after responses exist.',
            ]);
        }
    }
}
