<?php

namespace App\Modules\Validation\Actions;

use App\Models\Survey;
use App\Models\SurveyValidationRound;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CreateSurveyValidationRoundAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Survey $survey, array $attributes, ?Request $request = null): SurveyValidationRound
    {
        Gate::forUser($user)->authorize('manageValidation', $survey);

        $round = SurveyValidationRound::create([
            'survey_id' => $survey->getKey(),
            'research_project_id' => $survey->project_id,
            'created_by' => $user->getKey(),
            'title' => (string) $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'method' => (string) ($attributes['method'] ?? SurveyValidationRound::METHOD_EXPERT_JUDGMENT),
            'rating_scale_min' => (int) ($attributes['rating_scale_min'] ?? 1),
            'rating_scale_max' => (int) ($attributes['rating_scale_max'] ?? 5),
            'status' => (string) ($attributes['status'] ?? SurveyValidationRound::STATUS_DRAFT),
            'instructions' => $attributes['instructions'] ?? null,
            'starts_at' => $attributes['starts_at'] ?? null,
            'ends_at' => $attributes['ends_at'] ?? null,
        ]);

        $this->activityLogger->log('survey_validation_round.created', $user, $survey->project, $round, $this->metadata($round), $request);

        return $round;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(SurveyValidationRound $round): array
    {
        return [
            'survey_validation_round_id' => $round->getKey(),
            'survey_id' => $round->survey_id,
            'research_project_id' => $round->research_project_id,
            'status' => $round->status,
        ];
    }
}
