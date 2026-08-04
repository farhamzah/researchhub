<?php

namespace App\Modules\ReviewLinks\Services;

use App\Models\ReviewLink;
use App\Models\ReviewLinkAccessLog;
use App\Modules\ReviewLinks\DTOs\ReviewLinkResolution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ReviewLinkResolver
{
    public function __construct(private readonly ReviewLinkAccessLogger $accessLogger) {}

    public function resolveForView(string $token, Request $request): ReviewLinkResolution
    {
        $reviewLink = $this->findByToken($token);

        $this->accessLogger->log(
            $reviewLink,
            ReviewLinkAccessLog::ACTION_ACCESS_ATTEMPT,
            $reviewLink ? ReviewLinkAccessLog::RESULT_ALLOWED : ReviewLinkAccessLog::RESULT_BLOCKED,
            $request,
        );

        $resolution = $this->guard($reviewLink, $request);

        if (! $resolution->allowed) {
            return $resolution;
        }

        if ($reviewLink->hasPassword() && ! $this->hasPasswordSession($request, $reviewLink)) {
            $this->accessLogger->log(
                $reviewLink,
                ReviewLinkAccessLog::ACTION_PASSWORD_REQUIRED,
                ReviewLinkAccessLog::RESULT_BLOCKED,
                $request,
            );

            return new ReviewLinkResolution(
                reviewLink: $reviewLink,
                status: 'password_required',
                message: 'Password diperlukan.',
                requiresPassword: true,
            );
        }

        $reviewLink->markAccessed();

        $this->accessLogger->log(
            $reviewLink,
            ReviewLinkAccessLog::ACTION_ACCESS_GRANTED,
            ReviewLinkAccessLog::RESULT_ALLOWED,
            $request,
        );

        return new ReviewLinkResolution(
            reviewLink: $reviewLink->fresh(['document.category', 'document.currentVersion', 'documentVersion']),
            status: 'allowed',
            message: 'Review link ready.',
            allowed: true,
        );
    }

    public function resolveForAction(string $token, Request $request): ReviewLinkResolution
    {
        $reviewLink = $this->findByToken($token);
        $resolution = $this->guard($reviewLink, $request);

        if (! $resolution->allowed) {
            return $resolution;
        }

        if ($reviewLink->hasPassword() && ! $this->hasPasswordSession($request, $reviewLink)) {
            $this->accessLogger->log(
                $reviewLink,
                ReviewLinkAccessLog::ACTION_ACCESS_DENIED,
                ReviewLinkAccessLog::RESULT_BLOCKED,
                $request,
                ['reason' => 'password_required'],
            );

            return new ReviewLinkResolution(
                reviewLink: $reviewLink,
                status: 'password_required',
                message: 'Password diperlukan.',
                requiresPassword: true,
            );
        }

        return new ReviewLinkResolution(
            reviewLink: $reviewLink->fresh(['document.category', 'document.currentVersion', 'documentVersion']),
            status: 'allowed',
            message: 'Review link ready.',
            allowed: true,
        );
    }

    public function attemptPassword(string $token, string $password, Request $request): ReviewLinkResolution
    {
        $reviewLink = $this->findByToken($token);
        $resolution = $this->guard($reviewLink, $request);

        if (! $resolution->allowed) {
            return $resolution;
        }

        if (! $reviewLink->hasPassword()) {
            return new ReviewLinkResolution($reviewLink, 'allowed', 'Password is not required.', allowed: true);
        }

        if (! Hash::check($password, $reviewLink->password_hash)) {
            $this->accessLogger->log(
                $reviewLink,
                ReviewLinkAccessLog::ACTION_PASSWORD_FAILED,
                ReviewLinkAccessLog::RESULT_FAILED,
                $request,
            );

            return new ReviewLinkResolution($reviewLink, 'password_failed', 'The password is incorrect.');
        }

        $request->session()->put($this->passwordSessionKey($reviewLink), true);

        $this->accessLogger->log(
            $reviewLink,
            ReviewLinkAccessLog::ACTION_PASSWORD_PASSED,
            ReviewLinkAccessLog::RESULT_ALLOWED,
            $request,
        );

        return new ReviewLinkResolution($reviewLink, 'allowed', 'Password accepted.', allowed: true);
    }

    public function findByToken(string $token): ?ReviewLink
    {
        return ReviewLink::query()
            ->with(['document.category', 'document.currentVersion', 'documentVersion'])
            ->where('token_hash', ReviewLink::hashToken($token))
            ->first();
    }

    private function guard(?ReviewLink $reviewLink, Request $request): ReviewLinkResolution
    {
        if ($reviewLink === null) {
            $this->accessLogger->log(
                null,
                ReviewLinkAccessLog::ACTION_INVALID_TOKEN,
                ReviewLinkAccessLog::RESULT_BLOCKED,
                $request,
            );

            return new ReviewLinkResolution(null, 'invalid', 'Link review tidak tersedia.');
        }

        if ($reviewLink->isRevoked()) {
            $this->accessLogger->log(
                $reviewLink,
                ReviewLinkAccessLog::ACTION_REVOKED,
                ReviewLinkAccessLog::RESULT_BLOCKED,
                $request,
            );

            return new ReviewLinkResolution($reviewLink, 'revoked', 'Link review tidak tersedia.');
        }

        if ($reviewLink->isExpired()) {
            $this->accessLogger->log(
                $reviewLink,
                ReviewLinkAccessLog::ACTION_EXPIRED,
                ReviewLinkAccessLog::RESULT_BLOCKED,
                $request,
            );

            return new ReviewLinkResolution($reviewLink, 'expired', 'Link review tidak tersedia.');
        }

        if ($reviewLink->status !== ReviewLink::STATUS_ACTIVE || $reviewLink->accessLimitReached()) {
            $this->accessLogger->log(
                $reviewLink,
                ReviewLinkAccessLog::ACTION_ACCESS_DENIED,
                ReviewLinkAccessLog::RESULT_BLOCKED,
                $request,
                ['reason' => $reviewLink->status !== ReviewLink::STATUS_ACTIVE ? 'inactive_status' : 'access_limit'],
            );

            return new ReviewLinkResolution($reviewLink, 'blocked', 'Link review tidak tersedia.');
        }

        return new ReviewLinkResolution($reviewLink, 'allowed', 'Review link ready.', allowed: true);
    }

    private function hasPasswordSession(Request $request, ReviewLink $reviewLink): bool
    {
        return (bool) $request->session()->get($this->passwordSessionKey($reviewLink), false);
    }

    private function passwordSessionKey(ReviewLink $reviewLink): string
    {
        return 'review_link_password_'.$reviewLink->getKey();
    }
}
