<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentCategory;
use App\Models\DocumentComment;
use App\Models\DocumentVersion;
use App\Models\ProjectMember;
use App\Models\ResearchProject;
use App\Models\ReviewLink;
use App\Models\ReviewLinkAccessLog;
use App\Models\User;
use App\Modules\Documents\Actions\AddDocumentVersionAction;
use App\Modules\Documents\Actions\CreateDocumentAction;
use App\Modules\Documents\DTOs\DocumentUploadData;
use App\Modules\ReviewLinks\Actions\CreateReviewLinkAction;
use App\Modules\ReviewLinks\Actions\RevokeReviewLinkAction;
use Database\Seeders\DocumentCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewLinkSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_link_creation_stores_hashes_only_and_logs_owner_activity(): void
    {
        [$owner, $document] = $this->documentFixture();

        $result = app(CreateReviewLinkAction::class)->handle($owner, $document, [
            'label' => 'Supervisor review',
            'password' => 'review-password',
            'reviewer_name' => 'Dr Reviewer',
            'permissions' => [
                'comment' => true,
                'approve' => true,
            ],
            'expires_at' => now()->addDay(),
        ]);

        $raw = DB::table('review_links')->where('id', $result->reviewLink->id)->first();
        $metadata = ActivityLog::where('action', 'review_link.created')->firstOrFail()->metadata;

        $this->assertTrue(Str::isUuid($result->reviewLink->id));
        $this->assertSame(64, strlen($result->rawToken));
        $this->assertNotSame($result->rawToken, $raw->token_hash);
        $this->assertSame(ReviewLink::hashToken($result->rawToken), $raw->token_hash);
        $this->assertTrue(Hash::check('review-password', $raw->password_hash));
        $this->assertNotSame('review-password', $raw->password_hash);
        $this->assertStringContainsString($result->rawToken, $result->url);
        $this->assertStringNotContainsString($result->rawToken, json_encode($metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('review-password', json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    public function test_supervisor_can_create_review_link_but_viewer_cannot(): void
    {
        [$owner, $document, $project] = $this->documentFixture();
        $supervisor = User::factory()->create();
        $viewer = User::factory()->create();

        $this->activeMember($project, $supervisor, ProjectMember::ROLE_SUPERVISOR);
        $this->activeMember($project, $viewer, ProjectMember::ROLE_VIEWER);

        $created = app(CreateReviewLinkAction::class)->handle($supervisor, $document, [
            'label' => 'Supervisor link',
        ]);

        $this->assertInstanceOf(ReviewLink::class, $created->reviewLink);

        $this->expectException(AuthorizationException::class);

        app(CreateReviewLinkAction::class)->handle($viewer, $document, [
            'label' => 'Viewer link',
        ]);
    }

    public function test_super_admin_can_revoke_review_link_and_activity_is_logged(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $document] = $this->documentFixture();
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $result = app(CreateReviewLinkAction::class)->handle($owner, $document);

        $reviewLink = app(RevokeReviewLinkAction::class)->handle($superAdmin, $result->reviewLink);

        $this->assertSame(ReviewLink::STATUS_REVOKED, $reviewLink->status);
        $this->assertNotNull($reviewLink->revoked_at);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $superAdmin->id,
            'action' => 'review_link.revoked',
        ]);
    }

    public function test_invalid_expired_revoked_and_limited_links_show_safe_state(): void
    {
        [$owner, $document] = $this->documentFixture();

        $this->get('/review/not-a-real-token')
            ->assertNotFound()
            ->assertSee('Link review tidak tersedia')
            ->assertDontSee($document->title);

        $expired = app(CreateReviewLinkAction::class)->handle($owner, $document, [
            'expires_at' => now()->subMinute(),
        ]);

        $this->get($expired->url)
            ->assertForbidden()
            ->assertSee('Link review tidak tersedia')
            ->assertDontSee($document->title);

        $revoked = app(CreateReviewLinkAction::class)->handle($owner, $document);
        $revoked->reviewLink->markRevoked();

        $this->get($revoked->url)
            ->assertForbidden()
            ->assertSee('Link review tidak tersedia')
            ->assertDontSee($document->title);

        $limited = app(CreateReviewLinkAction::class)->handle($owner, $document, [
            'max_access_count' => 1,
        ]);

        $this->get($limited->url)->assertOk()->assertSee($document->title);
        $this->get($limited->url)
            ->assertForbidden()
            ->assertSee('Link review tidak tersedia');
        $this->assertSame(1, $limited->reviewLink->fresh()->access_count);
        $this->assertDatabaseHas('review_link_access_logs', ['action' => ReviewLinkAccessLog::ACTION_INVALID_TOKEN]);
        $this->assertDatabaseHas('review_link_access_logs', ['action' => ReviewLinkAccessLog::ACTION_EXPIRED]);
        $this->assertDatabaseHas('review_link_access_logs', ['action' => ReviewLinkAccessLog::ACTION_REVOKED]);
    }

    public function test_password_protected_review_link_requires_password_and_never_logs_password(): void
    {
        [$owner, $document] = $this->documentFixture();
        $result = app(CreateReviewLinkAction::class)->handle($owner, $document, [
            'password' => 'secret-review-password',
        ]);

        $this->get($result->url)
            ->assertOk()
            ->assertSee('Password diperlukan')
            ->assertDontSee($document->title);

        $this->post(route('review.password', ['token' => $result->rawToken]), [
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');

        $this->post(route('review.password', ['token' => $result->rawToken]), [
            'password' => 'secret-review-password',
        ])->assertRedirect($result->url);

        $this->get($result->url)
            ->assertOk()
            ->assertSee($document->title);

        $logs = ReviewLinkAccessLog::all()->map(fn (ReviewLinkAccessLog $log) => json_encode($log->toArray(), JSON_THROW_ON_ERROR))->implode("\n");

        $this->assertStringNotContainsString('secret-review-password', $logs);
        $this->assertStringNotContainsString('wrong-password', $logs);
        $this->assertDatabaseHas('review_link_access_logs', ['action' => ReviewLinkAccessLog::ACTION_PASSWORD_FAILED]);
        $this->assertDatabaseHas('review_link_access_logs', ['action' => ReviewLinkAccessLog::ACTION_PASSWORD_PASSED]);
    }

    public function test_comment_and_decision_require_explicit_permissions(): void
    {
        [$owner, $document] = $this->documentFixture();
        $blocked = app(CreateReviewLinkAction::class)->handle($owner, $document);

        $this->post(route('review.comments.store', ['token' => $blocked->rawToken]), [
            'comment' => 'Blocked comment',
        ])->assertForbidden();
        $this->post(route('review.decision.store', ['token' => $blocked->rawToken]), [
            'decision' => DocumentApproval::DECISION_APPROVED,
        ])->assertForbidden();

        $allowed = app(CreateReviewLinkAction::class)->handle($owner, $document, [
            'reviewer_name' => 'External Reviewer',
            'reviewer_email' => 'reviewer@example.test',
            'permissions' => [
                'comment' => true,
                'approve' => true,
                'request_revision' => true,
            ],
        ]);

        $this->post(route('review.comments.store', ['token' => $allowed->rawToken]), [
            'comment' => 'Please clarify the method section.',
        ])->assertRedirect($allowed->url);

        $this->post(route('review.decision.store', ['token' => $allowed->rawToken]), [
            'decision' => DocumentApproval::DECISION_REVISION_REQUIRED,
            'notes' => 'Revision needed.',
        ])->assertRedirect($allowed->url);

        $comment = DocumentComment::firstOrFail();
        $approval = DocumentApproval::firstOrFail();

        $this->assertNull($comment->user_id);
        $this->assertSame('External Reviewer', $comment->author_name);
        $this->assertNull($approval->reviewer_id);
        $this->assertSame(DocumentApproval::DECISION_REVISION_REQUIRED, $approval->decision);
        $this->assertSame(Document::STATUS_REVISION_REQUIRED, $document->fresh()->status);
        $this->assertDatabaseHas('review_link_access_logs', ['action' => ReviewLinkAccessLog::ACTION_COMMENT_CREATED]);
        $this->assertDatabaseHas('review_link_access_logs', ['action' => ReviewLinkAccessLog::ACTION_APPROVAL_CREATED]);
    }

    public function test_download_is_blocked_unless_permission_allows(): void
    {
        [$owner, $document] = $this->documentFixture();

        $blocked = app(CreateReviewLinkAction::class)->handle($owner, $document, [
            'permissions' => ['download' => false],
        ]);

        $this->get(route('review.download', ['token' => $blocked->rawToken]))
            ->assertForbidden();

        $allowed = app(CreateReviewLinkAction::class)->handle($owner, $document, [
            'permissions' => ['download' => true],
        ]);

        $this->get(route('review.download', ['token' => $allowed->rawToken]))
            ->assertRedirect('https://drive.example.test/download/1');

        $this->assertDatabaseHas('review_link_access_logs', [
            'action' => ReviewLinkAccessLog::ACTION_DOWNLOAD_REQUESTED,
            'result' => ReviewLinkAccessLog::RESULT_ALLOWED,
        ]);
    }

    /**
     * @return array{0: User, 1: Document, 2: ResearchProject}
     */
    private function documentFixture(): array
    {
        $this->seed(DocumentCategorySeeder::class);

        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Review Link Study',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $category = DocumentCategory::where('slug', 'proposal')->firstOrFail();
        $document = app(CreateDocumentAction::class)->handle($owner, $project, $category, [
            'title' => 'Secure Review Document',
            'description' => 'A scoped document for review links.',
        ]);

        app(AddDocumentVersionAction::class)->handle($owner, $document, new DocumentUploadData(
            fileName: 'secure-review-document.pdf',
            mimeType: 'application/pdf',
            originalFileName: 'secure-review-document.pdf',
            fileExtension: 'pdf',
            fileSize: 4096,
            checksum: hash('sha256', 'review link fake file'),
            driveFileId: 'drive-file-review-1',
            driveFolderId: 'drive-folder-review-1',
            webViewLink: 'https://drive.example.test/file/1',
            webDownloadLink: 'https://drive.example.test/download/1',
            storageStatus: DocumentVersion::STORAGE_STATUS_FAKE,
        ));

        return [$owner, $document->fresh(), $project];
    }

    private function activeMember(ResearchProject $project, User $user, string $role): ProjectMember
    {
        return ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);
    }
}
