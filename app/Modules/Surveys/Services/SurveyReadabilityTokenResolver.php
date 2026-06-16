<?php

namespace App\Modules\Surveys\Services;

use App\Models\SurveyReadabilityParticipant;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;

class SurveyReadabilityTokenResolver
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function resolve(string $token, ?Request $request = null, bool $markOpened = false): ?SurveyReadabilityParticipant
    {
        $participant = SurveyReadabilityParticipant::query()
            ->with([
                'round.survey.project',
                'round.survey.pages.questions',
                'round.survey.questions',
                'response.questionFeedback.question',
            ])
            ->where('token_hash', SurveyReadabilityParticipant::hashToken($token))
            ->first();

        if (! $participant) {
            return null;
        }

        if ($markOpened && $participant->isAccessible() && $participant->opened_at === null) {
            $participant->markOpened();

            $this->activityLogger->log('survey_readability_link.opened', null, $participant->round->survey->project, $participant, [
                'survey_readability_round_id' => $participant->survey_readability_round_id,
                'survey_readability_participant_id' => $participant->getKey(),
                'survey_id' => $participant->survey_id,
                'research_project_id' => $participant->round->survey->project_id,
                'status' => $participant->status,
            ], $request);
        }

        return $participant->refresh()->load([
            'round.survey.project',
            'round.survey.pages.questions',
            'round.survey.questions',
            'response.questionFeedback.question',
        ]);
    }
}
