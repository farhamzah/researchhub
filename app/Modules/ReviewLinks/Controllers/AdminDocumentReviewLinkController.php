<?php

namespace App\Modules\ReviewLinks\Controllers;

use App\Models\Document;
use App\Models\ReviewLink;
use App\Modules\ReviewLinks\Actions\CreateReviewLinkAction;
use App\Modules\ReviewLinks\Actions\RevokeReviewLinkAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminDocumentReviewLinkController extends Controller
{
    public function index(Document $document)
    {
        Gate::authorize('createReviewLink', $document);

        return view('review-links.admin.index', [
            'document' => $document->load(['project', 'category', 'currentVersion', 'versions']),
            'reviewLinks' => $document->reviewLinks()
                ->with('documentVersion')
                ->latest()
                ->get(),
            'permissionPresets' => $this->permissionPresets(),
            'defaultPermissions' => config('researchhub_review_links.default_permissions', []),
        ]);
    }

    public function store(Document $document, Request $request, CreateReviewLinkAction $createReviewLink): RedirectResponse
    {
        Gate::authorize('createReviewLink', $document);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'reviewer_name' => ['nullable', 'string', 'max:255'],
            'reviewer_email' => ['nullable', 'email', 'max:255'],
            'expires_at' => ['required', 'date', 'after:now'],
            'permission_preset' => ['required', Rule::in(array_keys($this->permissionPresets()))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['boolean'],
            'password' => ['nullable', 'string', 'max:200'],
            'max_access_count' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'document_version_id' => ['nullable', 'string', Rule::exists('document_versions', 'id')->where('document_id', $document->getKey())],
        ]);

        $password = filled($data['password'] ?? null) ? (string) $data['password'] : null;

        $result = $createReviewLink->handle($request->user(), $document, [
            'label' => $data['label'] ?? null,
            'reviewer_name' => $data['reviewer_name'] ?? null,
            'reviewer_email' => $data['reviewer_email'] ?? null,
            'expires_at' => Carbon::parse($data['expires_at']),
            'permissions' => $this->resolvePermissions(
                (string) $data['permission_preset'],
                $data['permissions'] ?? [],
            ),
            'password' => $password,
            'max_access_count' => $data['max_access_count'] ?? null,
            'document_version_id' => $data['document_version_id'] ?? null,
        ], $request);

        return redirect()
            ->route('admin.documents.review-links.index', ['document' => $document])
            ->with('generated_review_url', $result->url)
            ->with('status', 'review-link-created');
    }

    public function revoke(Document $document, ReviewLink $reviewLink, Request $request, RevokeReviewLinkAction $revokeReviewLink): RedirectResponse
    {
        abort_unless($reviewLink->document_id === $document->getKey(), 404);

        $revokeReviewLink->handle($request->user(), $reviewLink, $request);

        return redirect()
            ->route('admin.documents.review-links.index', ['document' => $document])
            ->with('status', 'review-link-revoked');
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function permissionPresets(): array
    {
        return [
            'view_only' => [
                'view' => true,
                'comment' => false,
                'approve' => false,
                'request_revision' => false,
                'download' => false,
                'view_version_history' => false,
            ],
            'comment_only' => [
                'view' => true,
                'comment' => true,
                'approve' => false,
                'request_revision' => false,
                'download' => false,
                'view_version_history' => false,
            ],
            'comment_request_revision' => [
                'view' => true,
                'comment' => true,
                'approve' => false,
                'request_revision' => true,
                'download' => false,
                'view_version_history' => false,
            ],
            'approve_revision' => [
                'view' => true,
                'comment' => true,
                'approve' => true,
                'request_revision' => true,
                'download' => false,
                'view_version_history' => false,
            ],
            'download_allowed' => [
                'view' => true,
                'comment' => true,
                'approve' => false,
                'request_revision' => true,
                'download' => true,
                'view_version_history' => false,
            ],
            'custom' => config('researchhub_review_links.default_permissions', []),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, bool>
     */
    private function resolvePermissions(string $preset, array $overrides): array
    {
        $permissions = $this->permissionPresets()[$preset];

        if ($preset === 'custom') {
            foreach (config('researchhub_review_links.permission_keys', []) as $key) {
                if (array_key_exists($key, $overrides)) {
                    $permissions[$key] = (bool) $overrides[$key];
                }
            }
        }

        $permissions['view'] = true;

        return $permissions;
    }
}
