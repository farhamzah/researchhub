<?php

namespace App\Modules\Surveys\Actions;

use App\Models\SurveyIndicator;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\SurveyScoringConfigSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateSurveyIndicatorAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SurveyScoringConfigSafetyService $safetyService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, SurveyIndicator $indicator, array $attributes, ?Request $request = null): SurveyIndicator
    {
        $indicator->loadMissing('survey.project');
        Gate::forUser($user)->authorize('manageScoring', $indicator->survey);
        $this->safetyService->ensureCanChangeScoring($indicator->survey);

        $slug = Str::slug((string) ($attributes['slug'] ?? $attributes['name'] ?? $indicator->slug)) ?: $indicator->slug;
        validator(['slug' => $slug], [
            'slug' => [
                'required',
                'max:255',
                Rule::unique('survey_indicators', 'slug')->where('survey_id', $indicator->survey_id)->ignore($indicator->getKey()),
            ],
        ])->validate();
        $this->ensureScaleBelongsToSurvey($indicator, $attributes['survey_scale_id'] ?? null);

        $indicator->fill([
            'survey_scale_id' => $attributes['survey_scale_id'] ?? null,
            'name' => (string) ($attributes['name'] ?? $indicator->name),
            'slug' => $slug,
            'description' => $attributes['description'] ?? null,
            'interpretation_rules' => $attributes['interpretation_rules'] ?? null,
            'sort_order' => (int) ($attributes['sort_order'] ?? $indicator->sort_order),
        ])->save();

        $this->activityLogger->log('survey.scoring_indicator_updated', $user, $indicator->survey->project, $indicator, [
            'survey_id' => $indicator->survey_id,
            'survey_indicator_id' => $indicator->getKey(),
            'survey_scale_id' => $indicator->survey_scale_id,
            'name' => $indicator->name,
        ], $request);

        return $indicator->fresh();
    }

    private function ensureScaleBelongsToSurvey(SurveyIndicator $indicator, ?string $scaleId): void
    {
        if ($scaleId !== null && $indicator->survey->scales()->whereKey($scaleId)->doesntExist()) {
            throw ValidationException::withMessages([
                'survey_scale_id' => 'Selected scale does not belong to this survey.',
            ]);
        }
    }
}
