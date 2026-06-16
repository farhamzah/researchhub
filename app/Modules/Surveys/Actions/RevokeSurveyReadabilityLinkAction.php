<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyReadabilityParticipant;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RevokeSurveyReadabilityLinkAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Survey $survey, SurveyReadabilityParticipant $participant, ?Request $request = null): SurveyReadabilityParticipant
    {
        $participant->loadMissing('round');
        abort_unless($participant->survey_id === $survey->getKey() && $participant->round?->survey_id === $survey->getKey(), 404);
        Gate::forUser($user)->authorize('manageValidation', $survey);

        $participant->markRevoked();

        $this->activityLogger->log('survey_readability_link.revoked', $user, $survey->project, $participant, [
            'survey_readability_round_id' => $participant->survey_readability_round_id,
            'survey_readability_participant_id' => $participant->getKey(),
            'survey_id' => $survey->getKey(),
            'research_project_id' => $survey->project_id,
            'status' => $participant->status,
        ], $request);

        return $participant;
    }
}
