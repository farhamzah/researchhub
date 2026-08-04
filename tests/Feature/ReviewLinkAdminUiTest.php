<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\ProjectMember;
use App\Models\ResearchProject;
use App\Models\ReviewLink;
use App\Models\User;
use App\Modules\Documents\Actions\AddDocumentVersionAction;
use App\Modules\Documents\Actions\CreateDocumentAction;
use App\Modules\Documents\DTOs\DocumentUploadData;
use App\Modules\ReviewLinks\Actions\CreateReviewLinkAction;
use Database\Seeders\DocumentCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReviewLinkAdminUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_review_link_admin_page(): void
    {
        [$owner, $document] = $this->documentFixture();

        $this->actingAs($owner)
            ->get(route('admin.documents.review-links.index', ['document' => $document]))
            ->assertOk()
            ->assertSee('Review Links')
            ->assertSee($document->title)
            ->assertSee('Create Review Link')  // UX-S10-09: section renamed for clarity
            ->assertSee('Existing Links');
    }

    public function test_create_review_link_shows_generated_url_once_and_stores_hash_only(): void
    {
        [$owner, $document] = $this->documentFixture();

        $response = $this->actingAs($owner)
            ->post(route('admin.documents.review-links.store', ['document' => $document]), [
                'label' => 'External committee',
                'reviewer_name' => 'Dr Reviewer',
                'reviewer_email' => 'reviewer@example.test',
                'expires_at' => now()->addDays(7)->format('Y-m-d\TH:i'),
                'permission_preset' => 'approve_revision',
                'max_access_count' => 3,
            ]);

        $response
            ->assertRedirect(route('admin.documents.review-links.index', ['document' => $document]))
            ->assertSessionHas('generated_review_url');

        $generatedUrl = $response->baseResponse->getSession()->get('generated_review_url');
        $rawToken = basename((string) parse_url($generatedUrl, PHP_URL_PATH));
        $reviewLink = ReviewLink::firstOrFail();
        $rawRow = DB::table('review_links')->where('id', $reviewLink->id)->first();

        $this->assertSame(64, strlen($rawToken));
        $this->assertNotSame($rawToken, $rawRow->token_hash);
        $this->assertSame(ReviewLink::hashToken($rawToken), $rawRow->token_hash);
        $this->assertTrue($reviewLink->allows('approve'));
        $this->assertTrue($reviewLink->allows('request_revision'));
        $this->assertSame(3, $reviewLink->max_access_count);

        $this->get(route('admin.documents.review-links.index', ['document' => $document]))
            ->assertOk()
            ->assertSee($generatedUrl, false);

        $this->get(route('admin.documents.review-links.index', ['document' => $document]))
            ->assertOk()
            ->assertDontSee($rawToken);
    }

    public function test_existing_review_link_listing_does_not_expose_token_or_password_hash(): void
    {
        [$owner, $document] = $this->documentFixture();
        $result = app(CreateReviewLinkAction::class)->handle($owner, $document, [
            'label' => 'Passworded link',
            'password' => 'review-password',
            'reviewer_email' => 'reviewer@example.test',
            'expires_at' => now()->addDays(7),
        ]);

        $rawRow = DB::table('review_links')->where('id', $result->reviewLink->id)->first();

        $this->actingAs($owner)
            ->get(route('admin.documents.review-links.index', ['document' => $document]))
            ->assertOk()
            ->assertSee('Passworded link')
            ->assertSee('reviewer@example.test')
            ->assertDontSee($result->rawToken)
            ->assertDontSee($rawRow->token_hash)
            ->assertDontSee($rawRow->password_hash);
    }

    public function test_custom_permissions_and_version_are_saved_from_admin_form(): void
    {
        [$owner, $document] = $this->documentFixture();
        $version = $document->versions()->firstOrFail();

        $this->actingAs($owner)
            ->post(route('admin.documents.review-links.store', ['document' => $document]), [
                'label' => 'Custom permissions',
                'expires_at' => now()->addDays(7)->format('Y-m-d\TH:i'),
                'permission_preset' => 'custom',
                'document_version_id' => $version->id,
                'permissions' => [
                    'comment' => '1',
                    'approve' => '0',
                    'request_revision' => '1',
                    'download' => '1',
                    'view_version_history' => '0',
                ],
            ])
            ->assertRedirect();

        $reviewLink = ReviewLink::firstOrFail();

        $this->assertSame($version->id, $reviewLink->document_version_id);
        $this->assertTrue($reviewLink->allows('view'));
        $this->assertTrue($reviewLink->allows('comment'));
        $this->assertFalse($reviewLink->allows('approve'));
        $this->assertTrue($reviewLink->allows('request_revision'));
        $this->assertTrue($reviewLink->allows('download'));
    }

    public function test_unauthorized_user_cannot_manage_review_links(): void
    {
        [$owner, $document] = $this->documentFixture();
        $viewer = User::factory()->create();
        ProjectMember::create([
            'project_id' => $document->project_id,
            'user_id' => $viewer->id,
            'role' => ProjectMember::ROLE_VIEWER,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);
        $result = app(CreateReviewLinkAction::class)->handle($owner, $document);

        $this->actingAs($viewer)
            ->get(route('admin.documents.review-links.index', ['document' => $document]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('admin.documents.review-links.store', ['document' => $document]), [
                'expires_at' => now()->addDays(7)->format('Y-m-d\TH:i'),
                'permission_preset' => 'view_only',
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('admin.documents.review-links.revoke', [
                'document' => $document,
                'reviewLink' => $result->reviewLink,
            ]))
            ->assertForbidden();
    }

    public function test_authorized_user_can_revoke_link_from_admin_page(): void
    {
        [$owner, $document] = $this->documentFixture();
        $result = app(CreateReviewLinkAction::class)->handle($owner, $document, [
            'expires_at' => now()->addDays(7),
            'permissions' => [
                'approve' => true,
            ],
        ]);

        $this->actingAs($owner)
            ->post(route('admin.documents.review-links.revoke', [
                'document' => $document,
                'reviewLink' => $result->reviewLink,
            ]))
            ->assertRedirect(route('admin.documents.review-links.index', ['document' => $document]));

        $this->assertSame(ReviewLink::STATUS_REVOKED, $result->reviewLink->fresh()->status);

        $this->get($result->url)
            ->assertForbidden()
            ->assertSee('Link review tidak tersedia')
            ->assertDontSee($document->title);
    }

    public function test_admin_form_requires_explicit_expiry(): void
    {
        [$owner, $document] = $this->documentFixture();

        $this->actingAs($owner)
            ->from(route('admin.documents.review-links.index', ['document' => $document]))
            ->post(route('admin.documents.review-links.store', ['document' => $document]), [
                'permission_preset' => 'view_only',
            ])
            ->assertRedirect(route('admin.documents.review-links.index', ['document' => $document]))
            ->assertSessionHasErrors('expires_at');
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
            'title' => 'Admin Review Link Study',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $category = DocumentCategory::where('slug', 'proposal')->firstOrFail();
        $document = app(CreateDocumentAction::class)->handle($owner, $project, $category, [
            'title' => 'Admin Review Document',
            'description' => 'A document for admin review link UI tests.',
        ]);

        app(AddDocumentVersionAction::class)->handle($owner, $document, new DocumentUploadData(
            fileName: 'admin-review-document.pdf',
            mimeType: 'application/pdf',
            originalFileName: 'admin-review-document.pdf',
            fileExtension: 'pdf',
            fileSize: 4096,
            checksum: hash('sha256', 'admin review link fake file'),
            driveFileId: 'drive-file-admin-review-1',
            driveFolderId: 'drive-folder-admin-review-1',
            webViewLink: 'https://drive.example.test/file/admin-review-1',
            webDownloadLink: 'https://drive.example.test/download/admin-review-1',
            storageStatus: DocumentVersion::STORAGE_STATUS_FAKE,
        ));

        return [$owner, $document->fresh(), $project];
    }
}
