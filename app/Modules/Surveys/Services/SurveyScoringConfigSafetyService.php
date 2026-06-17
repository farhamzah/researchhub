<?php

namespace App\Modules\Surveys\Services;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use Illuminate\Validation\ValidationException;

class SurveyScoringConfigSafetyService
{
    public function ensureCanChangeScoring(Survey $survey): void
    {
        if ($survey->responses()->where('status', 'submitted')->exists()) {
            throw ValidationException::withMessages([
                'scoring' => 'Scoring configuration cannot be changed after submitted responses exist.',
            ]);
        }
    }

    public function ensureQuestionCanBeScored(SurveyQuestion $question, bool $isScored): void
    {
        if (! $isScored) {
            return;
        }

        if (in_array($question->type, [
            SurveyQuestion::TYPE_HIDDEN,
            SurveyQuestion::TYPE_SHORT_TEXT,
            SurveyQuestion::TYPE_LONG_TEXT,
            SurveyQuestion::TYPE_DATE,
            SurveyQuestion::TYPE_CONSENT,
            SurveyQuestion::TYPE_LIKERT_MATRIX,
        ], true)) {
            throw ValidationException::withMessages([
                'is_scored' => 'This question type is collected for description/export and cannot be scored directly.',
            ]);
        }
    }
}
