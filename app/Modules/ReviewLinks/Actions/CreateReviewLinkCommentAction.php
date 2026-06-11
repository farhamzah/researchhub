<?php

namespace App\Modules\ReviewLinks\Actions;

use App\Models\DocumentComment;
use App\Models\ReviewLink;
use App\Models\ReviewLinkAccessLog;
use App\Modules\ReviewLinks\Services\ReviewLinkAccessLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class CreateReviewLinkCommentAction
{
    public function __construct(private readonly ReviewLinkAccessLogger $accessLogger) {}

    public function handle(ReviewLink $reviewLink, string $comment, ?Request $request = null): DocumentComment
    {
        if (! $reviewLink->allows('comment')) {
            $this->accessLogger->log(
                $reviewLink,
                ReviewLinkAccessLog::ACTION_ACCESS_DENIED,
                ReviewLinkAccessLog::RESULT_BLOCKED,
                $request,
                ['reason' => 'comment_permission_denied'],
            );

            throw new AuthorizationException('Comment is not allowed for this review link.');
        }

        $commentModel = DocumentComment::create([
            'document_id' => $reviewLink->document_id,
            'document_version_id' => $reviewLink->document_version_id,
            'user_id' => null,
            'author_name' => $reviewLink->reviewer_name,
            'author_email' => $reviewLink->reviewer_email,
            'comment' => $comment,
            'visibility' => DocumentComment::VISIBILITY_PROJECT,
        ]);

        $this->accessLogger->log(
            $reviewLink,
            ReviewLinkAccessLog::ACTION_COMMENT_CREATED,
            ReviewLinkAccessLog::RESULT_ALLOWED,
            $request,
            ['comment_id' => $commentModel->getKey()],
        );

        return $commentModel;
    }
}
