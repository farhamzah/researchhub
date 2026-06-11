<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\SurveyQuestionOptionsValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateSurveyQuestionAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SurveyQuestionOptionsValidationService $optionsValidation,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Survey $survey, array $attributes, ?Request $request = null): SurveyQuestion
    {
        Gate::forUser($user)->authorize('update', $survey);

        $questionKey = $this->questionKey($survey, $attributes);
        $type = (string) ($attributes['type'] ?? SurveyQuestion::TYPE_SHORT_TEXT);
        $this->validateBase($survey, $questionKey, $type, $attributes);
        $json = $this->optionsValidation->validate($type, $attributes['options_json'] ?? null, $attributes['settings_json'] ?? null);

        $question = $survey->questions()->create([
            'page_id' => $attributes['page_id'] ?? null,
            'question_key' => $questionKey,
            'type' => $type,
            'label' => (string) $attributes['label'],
            'help_text' => $attributes['help_text'] ?? null,
            'options' => $json['options'],
            'settings' => $json['settings'],
            'is_required' => (bool) ($attributes['is_required'] ?? false),
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
        ]);

        $this->activityLogger->log('survey.question_created', $user, $survey->project, $question, [
            'survey_id' => $survey->getKey(),
            'question_id' => $question->getKey(),
            'question_key' => $question->question_key,
            'type' => $question->type,
        ], $request);

        return $question;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function questionKey(Survey $survey, array $attributes): string
    {
        $key = trim((string) ($attributes['question_key'] ?? ''));

        if ($key !== '') {
            return $key;
        }

        $base = Str::slug((string) $attributes['label'], '_') ?: 'question';
        $key = $base;
        $counter = 2;

        while ($survey->questions()->where('question_key', $key)->exists()) {
            $key = "{$base}_{$counter}";
            $counter++;
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validateBase(Survey $survey, string $questionKey, string $type, array $attributes): void
    {
        validator([
            'question_key' => $questionKey,
            'type' => $type,
        ], [
            'question_key' => [
                'required',
                'max:100',
                'regex:/^[a-zA-Z0-9_-]+$/',
                Rule::unique('survey_questions', 'question_key')->where('survey_id', $survey->getKey()),
            ],
            'type' => ['required', Rule::in(config('researchhub_surveys.question_types', []))],
        ])->validate();

        if (filled($attributes['page_id'] ?? null) && $survey->pages()->whereKey($attributes['page_id'])->doesntExist()) {
            throw ValidationException::withMessages([
                'page_id' => 'Selected page does not belong to this survey.',
            ]);
        }
    }
}
