<?php

namespace App\Modules\Supervision\Actions;

use App\Models\ResearchProject;
use App\Models\SupervisionReviewLink;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RevokeSupervisionReviewLinkAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, ResearchProject $project, SupervisionReviewLink $reviewLink, ?Request $request = null): SupervisionReviewLink
    {
        $reviewLink->loadMissing('session');
        abort_unless($reviewLink->session?->research_project_id === $project->getKey(), 404);
        Gate::forUser($user)->authorize('manageSupervision', $project);

        $reviewLink->markRevoked();

        $this->activityLogger->log('supervision_link.revoked', $user, $project, $reviewLink, [
            'supervision_session_id' => $reviewLink->supervision_session_id,
            'supervision_review_link_id' => $reviewLink->getKey(),
            'research_project_id' => $project->getKey(),
            'expert_validator_id' => $reviewLink->expert_validator_id,
            'status' => $reviewLink->status,
        ], $request);

        return $reviewLink;
    }
}
