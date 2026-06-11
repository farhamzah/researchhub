<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentCategory;
use App\Models\DocumentComment;
use App\Models\DocumentVersion;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\Documents\Actions\AddDocumentVersionAction;
use App\Modules\Documents\Actions\CreateDocumentAction;
use App\Modules\Documents\Actions\DeleteDocumentAction;
use App\Modules\Documents\Actions\UpdateDocumentStatusAction;
use App\Modules\Documents\DTOs\DocumentUploadData;
use App\Modules\Documents\Services\DocumentFileValidationService;
use Database\Seeders\DocumentCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class DocumentVaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_category_seeder_is_idempotent_and_uses_uuid_primary_keys(): void
    {
        $this->seed(DocumentCategorySeeder::class);
        $this->seed(DocumentCategorySeeder::class);

        $category = DocumentCategory::where('slug', 'proposal')->firstOrFail();

        $this->assertSame(21, DocumentCategory::count());
        $this->assertTrue(Str::isUuid($category->id));
        $this->assertTrue($category->is_default);
        $this->assertDatabaseHas('document_categories', ['name' => 'Foto/Video']);
    }

    public function test_create_document_sets_safe_defaults_and_records_activity(): void
    {
        [$owner, $project, $category] = $this->projectFixture();

        $document = app(CreateDocumentAction::class)->handle($owner, $project, $category, [
            'title' => 'Proposal Draft',
            'description' => 'Initial proposal metadata only.',
            'tags' => ['proposal', 'draft'],
        ]);

        $metadata = ActivityLog::where('action', 'document.created')->firstOrFail()->metadata;

        $this->assertTrue(Str::isUuid($document->id));
        $this->assertSame(Document::STATUS_DRAFT, $document->status);
        $this->assertSame(Document::VISIBILITY_PRIVATE, $document->visibility);
        $this->assertSame('proposal-draft', $document->slug);
        $this->assertNull($document->current_version_id);
        $this->assertSame($document->id, $metadata['document_id']);
        $this->assertStringNotContainsString('file content', json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    public function test_document_versions_increment_and_update_current_version_without_file_binary_storage(): void
    {
        [$owner, $project, $category] = $this->projectFixture();

        $document = app(CreateDocumentAction::class)->handle($owner, $project, $category, [
            'title' => 'BAB I Draft',
        ]);

        $first = app(AddDocumentVersionAction::class)->handle($owner, $document, new DocumentUploadData(
            fileName: 'C:\\unsafe\\proposal-v1.pdf',
            mimeType: 'application/pdf',
            originalFileName: '..\\proposal-v1.pdf',
            fileExtension: 'pdf',
            fileSize: 2048,
            checksum: hash('sha256', 'fake document content v1'),
            driveFileId: 'fake-drive-file-1',
            driveFolderId: 'fake-drive-folder-1',
            webViewLink: 'https://drive.example.test/file/1',
            storageStatus: DocumentVersion::STORAGE_STATUS_FAKE,
        ));

        $second = app(AddDocumentVersionAction::class)->handle($owner, $document->fresh(), new DocumentUploadData(
            fileName: 'proposal-v2.pdf',
            mimeType: 'application/pdf',
            originalFileName: 'proposal-v2.pdf',
            fileExtension: 'pdf',
            fileSize: 3072,
            checksum: hash('sha256', 'fake document content v2'),
            driveFileId: 'fake-drive-file-2',
            driveFolderId: 'fake-drive-folder-1',
            webViewLink: 'https://drive.example.test/file/2',
            storageStatus: DocumentVersion::STORAGE_STATUS_FAKE,
        ));

        $document = $document->fresh();
        $metadata = ActivityLog::where('action', 'document.version_added')
            ->latest()
            ->firstOrFail()
            ->metadata;

        $this->assertTrue(Str::isUuid($first->id));
        $this->assertSame(1, $first->version_number);
        $this->assertSame(2, $second->version_number);
        $this->assertSame($second->id, $document->current_version_id);
        $this->assertSame('proposal-v1.pdf', $first->file_name);
        $this->assertSame('proposal-v1.pdf', $first->original_file_name);
        $this->assertSame(DocumentVersion::STORAGE_STATUS_FAKE, $second->storage_status);
        $this->assertDatabaseMissing('document_versions', ['file_name' => 'fake document content v1']);
        $this->assertStringNotContainsString('fake document content', json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    public function test_pending_upload_is_not_persisted_and_records_safe_failure(): void
    {
        [$owner, $project, $category] = $this->projectFixture();

        $document = app(CreateDocumentAction::class)->handle($owner, $project, $category, [
            'title' => 'Upload Failure',
        ]);

        try {
            app(AddDocumentVersionAction::class)->handle($owner, $document, new DocumentUploadData(
                fileName: 'pending.pdf',
                mimeType: 'application/pdf',
                storageStatus: DocumentVersion::STORAGE_STATUS_PENDING,
            ));
            $this->fail('Expected pending live upload to fail safely.');
        } catch (RuntimeException) {
            //
        }

        $metadata = ActivityLog::where('action', 'document.upload_failed')->firstOrFail()->metadata;

        $this->assertDatabaseCount('document_versions', 0);
        $this->assertNull($document->fresh()->current_version_id);
        $this->assertSame('RuntimeException', $metadata['reason']);
        $this->assertStringNotContainsString('pending.pdf', json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    public function test_document_status_update_and_delete_are_audit_logged(): void
    {
        [$owner, $project, $category] = $this->projectFixture();

        $document = app(CreateDocumentAction::class)->handle($owner, $project, $category, [
            'title' => 'Status Draft',
        ]);

        app(UpdateDocumentStatusAction::class)->handle($owner, $document, Document::STATUS_SUBMITTED);
        app(DeleteDocumentAction::class)->handle($owner, $document->fresh());

        $this->assertSame(Document::STATUS_SUBMITTED, $document->fresh()->status);
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('activity_logs', [
            'project_id' => $project->id,
            'action' => 'document.status_changed',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'project_id' => $project->id,
            'action' => 'document.deleted',
        ]);
    }

    public function test_file_validation_accepts_allowed_research_files_and_rejects_disallowed_files(): void
    {
        $service = app(DocumentFileValidationService::class);

        $pdf = UploadedFile::fake()->create('proposal.pdf', 128, 'application/pdf');
        $metadata = $service->metadataFromFile($pdf);

        $this->assertSame('proposal.pdf', $metadata->originalFileName);
        $this->assertSame('pdf', $metadata->fileExtension);
        $this->assertNotNull($metadata->checksum);

        $this->expectException(ValidationException::class);

        $service->validate(UploadedFile::fake()->create('payload.exe', 1, 'application/x-msdownload'));
    }

    public function test_comment_and_approval_models_use_uuid_primary_keys(): void
    {
        [$owner, $project, $category] = $this->projectFixture();

        $document = app(CreateDocumentAction::class)->handle($owner, $project, $category, [
            'title' => 'Review Draft',
        ]);

        $comment = DocumentComment::create([
            'document_id' => $document->id,
            'user_id' => $owner->id,
            'comment' => 'Needs clearer research gap.',
            'visibility' => DocumentComment::VISIBILITY_PROJECT,
        ]);

        $approval = DocumentApproval::create([
            'document_id' => $document->id,
            'reviewer_id' => $owner->id,
            'decision' => DocumentApproval::DECISION_REVISION_REQUIRED,
            'notes' => 'Revise before seminar.',
            'decided_at' => now(),
        ]);

        $this->assertTrue(Str::isUuid($comment->id));
        $this->assertTrue(Str::isUuid($approval->id));
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: DocumentCategory}
     */
    private function projectFixture(): array
    {
        $this->seed(DocumentCategorySeeder::class);

        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Document Vault Study',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $category = DocumentCategory::where('slug', 'proposal')->firstOrFail();

        return [$owner, $project, $category];
    }
}
