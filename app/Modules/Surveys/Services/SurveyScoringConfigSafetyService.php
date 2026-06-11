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
        if ($isScored && $question->type === SurveyQuestion::TYPE_HIDDEN) {
            throw ValidationException::withMessages([
                'is_scored' => 'Hidden questions cannot be scored.',
            ]);
        }
    }
}
