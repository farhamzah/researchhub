<?php

namespace App\Modules\Validation\Actions;

use App\Models\Survey;
use App\Models\SurveyValidationRound;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UpdateSurveyValidationRoundAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Survey $survey, SurveyValidationRound $round, array $attributes, ?Request $request = null): SurveyValidationRound
    {
        abort_unless($round->survey_id === $survey->getKey(), 404);
        Gate::forUser($user)->authorize('manageValidation', $survey);

        $round->update([
            'title' => (string) $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'method' => (string) ($attributes['method'] ?? $round->method),
            'rating_scale_min' => (int) ($attributes['rating_scale_min'] ?? $round->rating_scale_min),
            'rating_scale_max' => (int) ($attributes['rating_scale_max'] ?? $round->rating_scale_max),
            'status' => (string) ($attributes['status'] ?? $round->status),
            'instructions' => $attributes['instructions'] ?? null,
            'starts_at' => $attributes['starts_at'] ?? null,
            'ends_at' => $attributes['ends_at'] ?? null,
        ]);

        $this->activityLogger->log('survey_validation_round.updated', $user, $survey->project, $round, [
            'survey_validation_round_id' => $round->getKey(),
            'survey_id' => $round->survey_id,
            'research_project_id' => $round->research_project_id,
            'status' => $round->status,
        ], $request);

        return $round;
    }
}
