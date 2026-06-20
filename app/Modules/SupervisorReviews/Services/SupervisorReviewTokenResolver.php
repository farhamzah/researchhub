<?php

namespace App\Modules\SupervisorReviews\Services;

use App\Models\SurveySupervisorReviewer;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;

class SupervisorReviewTokenResolver
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function resolve(string $token, ?Request $request = null, bool $markOpened = false): ?SurveySupervisorReviewer
    {
        $reviewer = SurveySupervisorReviewer::query()
            ->with([
                'round.survey.project',
                'round.survey.pages.questions.scoring.indicator',
                'comments',
            ])
            ->where('token_hash', SurveySupervisorReviewer::hashToken($token))
            ->first();

        if (! $reviewer) {
            return null;
        }

        if ($markOpened && $reviewer->isAccessible()) {
            $reviewer->markOpened();

            $this->activityLogger->log('survey_supervisor_review_link.opened', null, $reviewer->round->project, $reviewer, [
                'survey_supervisor_review_round_id' => $reviewer->survey_supervisor_review_round_id,
                'survey_supervisor_reviewer_id' => $reviewer->getKey(),
                'survey_id' => $reviewer->round->survey_id,
                'research_project_id' => $reviewer->round->research_project_id,
                'status' => $reviewer->status,
            ], $request);
        }

        return $reviewer->refresh()->load([
            'round.survey.project',
            'round.survey.pages.questions.scoring.indicator',
            'comments',
        ]);
    }
}
