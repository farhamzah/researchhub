<?php

namespace App\Modules\Supervision\Services;

use App\Models\SupervisionReviewLink;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;

class SupervisionReviewTokenResolver
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function resolve(string $token, ?Request $request = null, bool $markOpened = false): ?SupervisionReviewLink
    {
        $reviewLink = SupervisionReviewLink::query()
            ->with([
                'session.project',
                'session.feedback.reviewLink.validator',
                'validator',
                'feedback',
            ])
            ->where('token_hash', SupervisionReviewLink::hashToken($token))
            ->first();

        if (! $reviewLink) {
            return null;
        }

        if ($reviewLink->isExpired()) {
            $reviewLink->markExpired();

            return $reviewLink->refresh()->load(['session.project', 'validator', 'feedback']);
        }

        if ($markOpened && $reviewLink->isAccessible() && $reviewLink->opened_at === null) {
            $reviewLink->markOpened();

            $this->activityLogger->log('supervision_link.opened', null, $reviewLink->session->project, $reviewLink, [
                'supervision_session_id' => $reviewLink->supervision_session_id,
                'supervision_review_link_id' => $reviewLink->getKey(),
                'research_project_id' => $reviewLink->session->research_project_id,
                'expert_validator_id' => $reviewLink->expert_validator_id,
                'status' => $reviewLink->status,
            ], $request);
        }

        return $reviewLink->refresh()->load(['session.project', 'validator', 'feedback']);
    }
}
