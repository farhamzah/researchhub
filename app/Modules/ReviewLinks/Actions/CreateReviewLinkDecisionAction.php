<?php

namespace App\Modules\ReviewLinks\Actions;

use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\ReviewLink;
use App\Models\ReviewLinkAccessLog;
use App\Modules\ReviewLinks\Services\ReviewLinkAccessLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CreateReviewLinkDecisionAction
{
    public function __construct(private readonly ReviewLinkAccessLogger $accessLogger) {}

    public function handle(ReviewLink $reviewLink, string $decision, ?string $notes = null, ?Request $request = null): DocumentApproval
    {
        $permission = match ($decision) {
            DocumentApproval::DECISION_APPROVED => 'approve',
            DocumentApproval::DECISION_REVISION_REQUIRED => 'request_revision',
            default => throw new InvalidArgumentException('Invalid review decision.'),
        };

        if (! $reviewLink->allows($permission)) {
            $this->accessLogger->log(
                $reviewLink,
                ReviewLinkAccessLog::ACTION_ACCESS_DENIED,
                ReviewLinkAccessLog::RESULT_BLOCKED,
                $request,
                ['reason' => "{$permission}_permission_denied"],
            );

            throw new AuthorizationException('Decision is not allowed for this review link.');
        }

        $approval = DocumentApproval::create([
            'document_id' => $reviewLink->document_id,
            'document_version_id' => $reviewLink->document_version_id,
            'reviewer_id' => null,
            'reviewer_name' => $reviewLink->reviewer_name,
            'reviewer_email' => $reviewLink->reviewer_email,
            'decision' => $decision,
            'notes' => $notes,
            'decided_at' => now(),
        ]);

        $reviewLink->document->forceFill([
            'status' => $decision === DocumentApproval::DECISION_APPROVED
                ? Document::STATUS_APPROVED
                : Document::STATUS_REVISION_REQUIRED,
        ])->save();

        $this->accessLogger->log(
            $reviewLink,
            ReviewLinkAccessLog::ACTION_APPROVAL_CREATED,
            ReviewLinkAccessLog::RESULT_ALLOWED,
            $request,
            [
                'approval_id' => $approval->getKey(),
                'decision' => $decision,
            ],
        );

        return $approval;
    }
}
