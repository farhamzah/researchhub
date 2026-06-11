<?php

namespace App\Modules\Surveys\Actions;

use App\Models\SurveyQuestion;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\SurveyQuestionOptionsValidationService;
use App\Modules\Surveys\Services\SurveyQuestionSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateSurveyQuestionAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SurveyQuestionOptionsValidationService $optionsValidation,
        private readonly SurveyQuestionSafetyService $safetyService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, SurveyQuestion $question, array $attributes, ?Request $request = null): SurveyQuestion
    {
        Gate::forUser($user)->authorize('update', $question->survey);
        $this->safetyService->ensureQuestionUpdateIsSafe($question, $attributes);

        $questionKey = (string) ($attributes['question_key'] ?? $question->question_key);
        $type = (string) ($attributes['type'] ?? $question->type);
        $this->validateBase($question, $questionKey, $type, $attributes);
        $json = $this->optionsValidation->validate($type, $attributes['options_json'] ?? null, $attributes['settings_json'] ?? null);

        $question->fill([
            'page_id' => $attributes['page_id'] ?? null,
            'question_key' => $questionKey,
            'type' => $type,
            'label' => (string) ($attributes['label'] ?? $question->label),
            'help_text' => $attributes['help_text'] ?? null,
            'options' => $json['options'],
            'settings' => $json['settings'],
            'is_required' => (bool) ($attributes['is_required'] ?? false),
            'sort_order' => (int) ($attributes['sort_order'] ?? $question->sort_order),
        ])->save();

        $this->activityLogger->log('survey.question_updated', $user, $question->survey->project, $question, [
            'survey_id' => $question->survey_id,
            'question_id' => $question->getKey(),
            'question_key' => $question->question_key,
            'type' => $question->type,
        ], $request);

        return $question->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validateBase(SurveyQuestion $question, string $questionKey, string $type, array $attributes): void
    {
        validator([
            'question_key' => $questionKey,
            'type' => $type,
        ], [
            'question_key' => [
                'required',
                'max:100',
                'regex:/^[a-zA-Z0-9_-]+$/',
                Rule::unique('survey_questions', 'question_key')
                    ->where('survey_id', $question->survey_id)
                    ->ignore($question->getKey()),
            ],
            'type' => ['required', Rule::in(config('researchhub_surveys.question_types', []))],
        ])->validate();

        if (filled($attributes['page_id'] ?? null) && $question->survey->pages()->whereKey($attributes['page_id'])->doesntExist()) {
            throw ValidationException::withMessages([
                'page_id' => 'Selected page does not belong to this survey.',
            ]);
        }
    }
}
