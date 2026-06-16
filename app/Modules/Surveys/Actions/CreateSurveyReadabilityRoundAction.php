<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyReadabilityRound;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CreateSurveyReadabilityRoundAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Survey $survey, array $attributes, ?Request $request = null): SurveyReadabilityRound
    {
        Gate::forUser($user)->authorize('manageValidation', $survey);

        $round = SurveyReadabilityRound::create([
            'survey_id' => $survey->getKey(),
            'created_by' => $user->getKey(),
            'title' => (string) $attributes['title'],
            'status' => (string) ($attributes['status'] ?? SurveyReadabilityRound::STATUS_OPEN),
            'target_participants' => $attributes['target_participants'] ?? 10,
            'starts_at' => $attributes['starts_at'] ?? null,
            'ends_at' => $attributes['ends_at'] ?? null,
            'instructions' => $attributes['instructions'] ?? null,
        ]);

        $this->activityLogger->log('survey_readability_round.created', $user, $survey->project, $round, [
            'survey_readability_round_id' => $round->getKey(),
            'survey_id' => $survey->getKey(),
            'research_project_id' => $survey->project_id,
            'status' => $round->status,
            'target_participants' => $round->target_participants,
        ], $request);

        return $round;
    }
}
