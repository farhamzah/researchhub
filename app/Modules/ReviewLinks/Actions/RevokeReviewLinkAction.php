<?php

namespace App\Modules\ReviewLinks\Actions;

use App\Models\ReviewLink;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RevokeReviewLinkAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, ReviewLink $reviewLink, ?Request $request = null): ReviewLink
    {
        Gate::forUser($user)->authorize('createReviewLink', $reviewLink->document);

        $reviewLink->markRevoked();

        $this->activityLogger->log(
            'review_link.revoked',
            $user,
            $reviewLink->project,
            $reviewLink,
            [
                'review_link_id' => $reviewLink->getKey(),
                'document_id' => $reviewLink->document_id,
            ],
            $request,
        );

        return $reviewLink;
    }
}
