<?php

namespace App\Modules\Validation\Services;

use App\Models\SurveyValidationAssignment;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;

class SurveyValidationTokenResolver
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function resolve(string $token, ?Request $request = null, bool $markOpened = false): ?SurveyValidationAssignment
    {
        $assignment = SurveyValidationAssignment::query()
            ->with([
                'round.survey.project',
                'round.survey.questions.scoring.indicator',
                'validator',
                'scores',
            ])
            ->where('token_hash', SurveyValidationAssignment::hashToken($token))
            ->first();

        if (! $assignment) {
            return null;
        }

        if ($assignment->isExpired()) {
            $assignment->markExpired();

            return $assignment;
        }

        if ($markOpened && $assignment->isAccessible() && $assignment->opened_at === null) {
            $assignment->markOpened();

            $this->activityLogger->log('survey_validation_link.opened', null, $assignment->round->project, $assignment, [
                'survey_validation_round_id' => $assignment->survey_validation_round_id,
                'survey_validation_assignment_id' => $assignment->getKey(),
                'survey_id' => $assignment->round->survey_id,
                'expert_validator_id' => $assignment->expert_validator_id,
                'research_project_id' => $assignment->round->research_project_id,
                'status' => $assignment->status,
            ], $request);
        }

        return $assignment->refresh()->load([
            'round.survey.project',
            'round.survey.questions.scoring.indicator',
            'validator',
            'scores',
        ]);
    }
}
