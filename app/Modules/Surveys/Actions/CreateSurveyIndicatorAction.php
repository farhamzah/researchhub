<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyIndicator;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\SurveyScoringConfigSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateSurveyIndicatorAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SurveyScoringConfigSafetyService $safetyService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Survey $survey, array $attributes, ?Request $request = null): SurveyIndicator
    {
        Gate::forUser($user)->authorize('manageScoring', $survey);
        $this->safetyService->ensureCanChangeScoring($survey);

        $slug = Str::slug((string) ($attributes['slug'] ?? $attributes['name'] ?? 'indicator')) ?: 'indicator';
        validator(['slug' => $slug], [
            'slug' => ['required', 'max:255', Rule::unique('survey_indicators', 'slug')->where('survey_id', $survey->getKey())],
        ])->validate();

        $this->ensureScaleBelongsToSurvey($survey, $attributes['survey_scale_id'] ?? null);

        $indicator = $survey->indicators()->create([
            'survey_scale_id' => $attributes['survey_scale_id'] ?? null,
            'name' => (string) $attributes['name'],
            'slug' => $slug,
            'description' => $attributes['description'] ?? null,
            'interpretation_rules' => $attributes['interpretation_rules'] ?? null,
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
        ]);

        $this->activityLogger->log('survey.scoring_indicator_created', $user, $survey->project, $indicator, [
            'survey_id' => $survey->getKey(),
            'survey_indicator_id' => $indicator->getKey(),
            'survey_scale_id' => $indicator->survey_scale_id,
            'name' => $indicator->name,
        ], $request);

        return $indicator;
    }

    private function ensureScaleBelongsToSurvey(Survey $survey, ?string $scaleId): void
    {
        if ($scaleId !== null && $survey->scales()->whereKey($scaleId)->doesntExist()) {
            throw ValidationException::withMessages([
                'survey_scale_id' => 'Selected scale does not belong to this survey.',
            ]);
        }
    }
}
