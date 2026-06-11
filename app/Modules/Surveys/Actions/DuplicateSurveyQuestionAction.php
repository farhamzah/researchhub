<?php

namespace App\Modules\Surveys\Actions;

use App\Models\SurveyQuestion;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DuplicateSurveyQuestionAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, SurveyQuestion $question, ?Request $request = null): SurveyQuestion
    {
        Gate::forUser($user)->authorize('update', $question->survey);

        $copy = $question->replicate();
        $copy->question_key = $this->nextCopyKey($question);
        $copy->label = $question->label.' Copy';
        $copy->sort_order = $question->sort_order + 1;
        $copy->save();

        $this->activityLogger->log('survey.question_duplicated', $user, $question->survey->project, $copy, [
            'survey_id' => $question->survey_id,
            'source_question_id' => $question->getKey(),
            'question_id' => $copy->getKey(),
            'question_key' => $copy->question_key,
        ], $request);

        return $copy;
    }

    private function nextCopyKey(SurveyQuestion $question): string
    {
        $base = $question->question_key.'_copy';
        $key = $base;
        $counter = 2;

        while ($question->survey->questions()->where('question_key', $key)->exists()) {
            $key = "{$base}_{$counter}";
            $counter++;
        }

        return $key;
    }
}
