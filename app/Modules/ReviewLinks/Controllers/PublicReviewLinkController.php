<?php

namespace App\Modules\ReviewLinks\Controllers;

use App\Models\DocumentApproval;
use App\Models\ReviewLinkAccessLog;
use App\Modules\ReviewLinks\Actions\CreateReviewLinkCommentAction;
use App\Modules\ReviewLinks\Actions\CreateReviewLinkDecisionAction;
use App\Modules\ReviewLinks\DTOs\ReviewLinkResolution;
use App\Modules\ReviewLinks\Services\ReviewLinkAccessLogger;
use App\Modules\ReviewLinks\Services\ReviewLinkResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class PublicReviewLinkController extends Controller
{
    public function show(string $token, Request $request, ReviewLinkResolver $resolver)
    {
        return $this->reviewResponse($resolver->resolveForView($token, $request), $token);
    }

    public function password(string $token, Request $request, ReviewLinkResolver $resolver)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'max:200'],
        ]);

        $resolution = $resolver->attemptPassword($token, (string) $data['password'], $request);

        if ($resolution->allowed) {
            return redirect()->route('review.show', ['token' => $token]);
        }

        if ($resolution->status === 'password_failed') {
            return back()->withErrors(['password' => 'The review password is incorrect.']);
        }

        return $this->reviewResponse($resolution, $token);
    }

    public function comment(
        string $token,
        Request $request,
        ReviewLinkResolver $resolver,
        CreateReviewLinkCommentAction $createComment,
    ): RedirectResponse {
        $resolution = $resolver->resolveForAction($token, $request);

        if (! $resolution->allowed) {
            abort(403);
        }

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $createComment->handle($resolution->reviewLink, (string) $data['comment'], $request);
        } catch (AuthorizationException) {
            abort(403);
        }

        return redirect()
            ->route('review.show', ['token' => $token])
            ->with('status', 'review-comment-created');
    }

    public function decision(
        string $token,
        Request $request,
        ReviewLinkResolver $resolver,
        CreateReviewLinkDecisionAction $createDecision,
    ): RedirectResponse {
        $resolution = $resolver->resolveForAction($token, $request);

        if (! $resolution->allowed) {
            abort(403);
        }

        $data = $request->validate([
            'decision' => ['required', Rule::in([
                DocumentApproval::DECISION_APPROVED,
                DocumentApproval::DECISION_REVISION_REQUIRED,
            ])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $createDecision->handle(
                $resolution->reviewLink,
                (string) $data['decision'],
                $data['notes'] ?? null,
                $request,
            );
        } catch (AuthorizationException) {
            abort(403);
        }

        return redirect()
            ->route('review.show', ['token' => $token])
            ->with('status', 'review-decision-created');
    }

    public function download(
        string $token,
        Request $request,
        ReviewLinkResolver $resolver,
        ReviewLinkAccessLogger $accessLogger,
    ) {
        $resolution = $resolver->resolveForAction($token, $request);

        if (! $resolution->allowed) {
            abort(403);
        }

        $reviewLink = $resolution->reviewLink;

        if (! $reviewLink->allows('download')) {
            $accessLogger->log(
                $reviewLink,
                ReviewLinkAccessLog::ACTION_ACCESS_DENIED,
                ReviewLinkAccessLog::RESULT_BLOCKED,
                $request,
                ['reason' => 'download_permission_denied'],
            );

            abort(403);
        }

        $version = $reviewLink->documentVersion ?: $reviewLink->document->currentVersion;

        if (blank($version?->web_download_link)) {
            $accessLogger->log(
                $reviewLink,
                ReviewLinkAccessLog::ACTION_DOWNLOAD_REQUESTED,
                ReviewLinkAccessLog::RESULT_FAILED,
                $request,
                ['reason' => 'download_link_unavailable'],
            );

            abort(404);
        }

        $accessLogger->log(
            $reviewLink,
            ReviewLinkAccessLog::ACTION_DOWNLOAD_REQUESTED,
            ReviewLinkAccessLog::RESULT_ALLOWED,
            $request,
            ['document_version_id' => $version->getKey()],
        );

        return redirect()->away($version->web_download_link);
    }

    private function reviewResponse(ReviewLinkResolution $resolution, string $token)
    {
        $status = match ($resolution->status) {
            'invalid' => 404,
            'allowed', 'password_required' => 200,
            default => 403,
        };

        return response()->view('review-links.show', [
            'resolution' => $resolution,
            'token' => $token,
            'reviewLink' => $resolution->reviewLink,
            'document' => $resolution->reviewLink?->document,
            'version' => $resolution->reviewLink?->documentVersion ?: $resolution->reviewLink?->document?->currentVersion,
            'versions' => $resolution->reviewLink?->allows('view_version_history')
                ? $resolution->reviewLink?->document?->versions()->latest('version_number')->get()
                : collect(),
        ], $status);
    }
}
