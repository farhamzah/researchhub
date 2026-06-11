<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyScale;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\SurveyScoringConfigSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateSurveyScaleAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SurveyScoringConfigSafetyService $safetyService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Survey $survey, array $attributes, ?Request $request = null): SurveyScale
    {
        Gate::forUser($user)->authorize('manageScoring', $survey);
        $this->safetyService->ensureCanChangeScoring($survey);

        $slug = $this->slug($attributes);
        validator(['slug' => $slug], [
            'slug' => ['required', 'max:255', Rule::unique('survey_scales', 'slug')->where('survey_id', $survey->getKey())],
        ])->validate();

        $scale = $survey->scales()->create([
            'name' => (string) $attributes['name'],
            'slug' => $slug,
            'description' => $attributes['description'] ?? null,
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            'settings' => $attributes['settings'] ?? null,
        ]);

        $this->activityLogger->log('survey.scoring_scale_created', $user, $survey->project, $scale, [
            'survey_id' => $survey->getKey(),
            'survey_scale_id' => $scale->getKey(),
            'name' => $scale->name,
        ], $request);

        return $scale;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function slug(array $attributes): string
    {
        return Str::slug((string) ($attributes['slug'] ?? $attributes['name'] ?? 'scale')) ?: 'scale';
    }
}
