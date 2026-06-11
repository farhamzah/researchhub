<?php

namespace App\Modules\ReviewLinks\Actions;

use App\Models\Document;
use App\Models\ReviewLink;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\ReviewLinks\DTOs\ReviewLinkCreationResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateReviewLinkAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Document $document, array $attributes = [], ?Request $request = null): ReviewLinkCreationResult
    {
        Gate::forUser($user)->authorize('createReviewLink', $document);

        $rawToken = Str::random(64);
        $permissions = $this->permissions($attributes['permissions'] ?? []);

        $reviewLink = ReviewLink::create([
            'project_id' => $document->project_id,
            'document_id' => $document->getKey(),
            'document_version_id' => $attributes['document_version_id'] ?? $document->current_version_id,
            'created_by' => $user->getKey(),
            'token_hash' => ReviewLink::hashToken($rawToken),
            'password_hash' => filled($attributes['password'] ?? null) ? Hash::make((string) $attributes['password']) : null,
            'label' => $attributes['label'] ?? null,
            'reviewer_name' => $attributes['reviewer_name'] ?? null,
            'reviewer_email' => $attributes['reviewer_email'] ?? null,
            'permissions' => $permissions,
            'status' => ReviewLink::STATUS_ACTIVE,
            'expires_at' => $attributes['expires_at'] ?? null,
            'max_access_count' => $attributes['max_access_count'] ?? null,
        ]);

        $this->activityLogger->log(
            'review_link.created',
            $user,
            $document->project,
            $reviewLink,
            [
                'review_link_id' => $reviewLink->getKey(),
                'document_id' => $document->getKey(),
                'permissions' => $permissions,
                'expires_at' => $reviewLink->expires_at?->toISOString(),
                'has_password' => $reviewLink->hasPassword(),
            ],
            $request,
        );

        return new ReviewLinkCreationResult(
            reviewLink: $reviewLink,
            rawToken: $rawToken,
            url: route('review.show', ['token' => $rawToken]),
        );
    }

    /**
     * @param  array<string, mixed>  $permissions
     * @return array<string, bool>
     */
    private function permissions(array $permissions): array
    {
        $allowedKeys = config('researchhub_review_links.permission_keys', []);
        $resolved = config('researchhub_review_links.default_permissions', []);

        foreach ($permissions as $key => $value) {
            if (! in_array($key, $allowedKeys, true)) {
                throw new InvalidArgumentException("Invalid review link permission [{$key}].");
            }

            $resolved[$key] = (bool) $value;
        }

        return $resolved;
    }
}
