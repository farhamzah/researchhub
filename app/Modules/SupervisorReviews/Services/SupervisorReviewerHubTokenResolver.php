<?php

namespace App\Modules\SupervisorReviews\Services;

use App\Models\SurveySupervisorReviewerHub;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;

class SupervisorReviewerHubTokenResolver
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function resolve(string $token, ?Request $request = null, bool $markOpened = false): ?SurveySupervisorReviewerHub
    {
        $hub = SurveySupervisorReviewerHub::query()
            ->with(['project', 'reviewers.round.survey.project', 'reviewers.comments'])
            ->where('token_hash', SurveySupervisorReviewerHub::hashToken($token))
            ->first();

        if (! $hub) {
            return null;
        }

        if ($markOpened && $hub->isAccessible() && $hub->opened_at === null) {
            $hub->markOpened();
            $this->activityLogger->log('survey_supervisor_reviewer_hub.opened', null, $hub->project, $hub, [
                'research_project_id' => $hub->research_project_id,
                'survey_supervisor_reviewer_hub_id' => $hub->getKey(),
                'supervisor_code' => $hub->supervisor_code,
            ], $request);
        }

        return $hub->refresh()->load(['project', 'reviewers.round.survey.project', 'reviewers.comments']);
    }
}
