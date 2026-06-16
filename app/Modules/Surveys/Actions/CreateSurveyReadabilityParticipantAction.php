<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyReadabilityRound;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CreateSurveyReadabilityParticipantAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Survey $survey, SurveyReadabilityRound $round, array $attributes, ?Request $request = null): SurveyReadabilityParticipant
    {
        abort_unless($round->survey_id === $survey->getKey(), 404);
        Gate::forUser($user)->authorize('manageValidation', $survey);

        $participant = SurveyReadabilityParticipant::create([
            'survey_readability_round_id' => $round->getKey(),
            'survey_id' => $survey->getKey(),
            'participant_name' => $attributes['participant_name'] ?? null,
            'participant_email' => $attributes['participant_email'] ?? null,
            'participant_type' => $attributes['participant_type'] ?? null,
            'institution' => $attributes['institution'] ?? null,
            'status' => SurveyReadabilityParticipant::STATUS_PENDING,
        ]);

        $this->activityLogger->log('survey_readability_participant.created', $user, $survey->project, $participant, [
            'survey_readability_round_id' => $round->getKey(),
            'survey_readability_participant_id' => $participant->getKey(),
            'survey_id' => $survey->getKey(),
            'research_project_id' => $survey->project_id,
            'participant_type' => $participant->participant_type,
            'status' => $participant->status,
        ], $request);

        return $participant;
    }
}
