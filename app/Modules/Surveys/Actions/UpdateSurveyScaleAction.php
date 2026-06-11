<?php

namespace App\Modules\Surveys\Actions;

use App\Models\SurveyScale;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\SurveyScoringConfigSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateSurveyScaleAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SurveyScoringConfigSafetyService $safetyService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, SurveyScale $scale, array $attributes, ?Request $request = null): SurveyScale
    {
        $scale->loadMissing('survey.project');
        Gate::forUser($user)->authorize('manageScoring', $scale->survey);
        $this->safetyService->ensureCanChangeScoring($scale->survey);

        $slug = Str::slug((string) ($attributes['slug'] ?? $attributes['name'] ?? $scale->slug)) ?: $scale->slug;
        validator(['slug' => $slug], [
            'slug' => [
                'required',
                'max:255',
                Rule::unique('survey_scales', 'slug')->where('survey_id', $scale->survey_id)->ignore($scale->getKey()),
            ],
        ])->validate();

        $scale->fill([
            'name' => (string) ($attributes['name'] ?? $scale->name),
            'slug' => $slug,
            'description' => $attributes['description'] ?? null,
            'sort_order' => (int) ($attributes['sort_order'] ?? $scale->sort_order),
            'settings' => $attributes['settings'] ?? null,
        ])->save();

        $this->activityLogger->log('survey.scoring_scale_updated', $user, $scale->survey->project, $scale, [
            'survey_id' => $scale->survey_id,
            'survey_scale_id' => $scale->getKey(),
            'name' => $scale->name,
        ], $request);

        return $scale->fresh();
    }
}
