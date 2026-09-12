<?php

namespace App\Modules\SupervisorReviews\Actions;

use App\Models\SurveySupervisorReviewerHub;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RevokeSupervisorReviewerHubAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $actor, SurveySupervisorReviewerHub $hub): void
    {
        $hub->loadMissing('project', 'reviewers.round.survey');
        $hub->reviewers->each(fn ($reviewer) => Gate::forUser($actor)->authorize('manageSupervisorReview', $reviewer->round->survey));

        DB::transaction(function () use ($actor, $hub): void {
            $hub->forceFill([
                'token_hash' => null,
                'token_created_at' => null,
                'revoked_at' => now(),
            ])->save();

            $this->activityLogger->log('survey_supervisor_reviewer_hub.revoked', $actor, $hub->project, $hub, [
                'research_project_id' => $hub->research_project_id,
                'survey_supervisor_reviewer_hub_id' => $hub->getKey(),
                'supervisor_code' => $hub->supervisor_code,
            ]);
        });
    }
}
