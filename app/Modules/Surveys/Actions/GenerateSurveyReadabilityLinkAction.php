<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyReadabilityParticipant;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\DTOs\SurveyReadabilityLinkGenerationResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class GenerateSurveyReadabilityLinkAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Survey $survey, SurveyReadabilityParticipant $participant, ?Request $request = null): SurveyReadabilityLinkGenerationResult
    {
        $participant->loadMissing('round');
        abort_unless($participant->survey_id === $survey->getKey() && $participant->round?->survey_id === $survey->getKey(), 404);
        Gate::forUser($user)->authorize('manageValidation', $survey);

        $rawToken = Str::random(64);

        $participant->forceFill([
            'token_hash' => SurveyReadabilityParticipant::hashToken($rawToken),
            'token_created_at' => now(),
            'opened_at' => null,
            'submitted_at' => null,
            'revoked_at' => null,
            'status' => SurveyReadabilityParticipant::STATUS_PENDING,
        ])->save();

        $this->activityLogger->log('survey_readability_link.generated', $user, $survey->project, $participant, [
            'survey_readability_round_id' => $participant->survey_readability_round_id,
            'survey_readability_participant_id' => $participant->getKey(),
            'survey_id' => $survey->getKey(),
            'research_project_id' => $survey->project_id,
            'status' => $participant->status,
        ], $request);

        return new SurveyReadabilityLinkGenerationResult(
            participant: $participant,
            rawToken: $rawToken,
            url: route('readability.survey.show', ['token' => $rawToken]),
        );
    }
}
