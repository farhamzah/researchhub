<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\ProjectMember;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\Documents\Actions\AddDocumentVersionAction;
use App\Modules\Documents\Actions\CreateDocumentAction;
use App\Modules\Documents\Actions\UpdateDocumentStatusAction;
use App\Modules\Documents\DTOs\DocumentUploadData;
use Database\Seeders\DocumentCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DocumentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_policy_allows_project_view_but_restricts_management_roles(): void
    {
        [$owner, $project, $category] = $this->projectFixture();
        $coResearcher = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();

        $this->activeMember($project, $coResearcher, ProjectMember::ROLE_CO_RESEARCHER);
        $this->activeMember($project, $viewer, ProjectMember::ROLE_VIEWER);

        $document = app(CreateDocumentAction::class)->handle($owner, $project, $category, [
            'title' => 'Protected Document',
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $document));
        $this->assertTrue(Gate::forUser($coResearcher)->allows('view', $document));
        $this->assertTrue(Gate::forUser($viewer)->allows('view', $document));
        $this->assertFalse(Gate::forUser($outsider)->allows('view', $document));

        $this->assertTrue(Gate::forUser($owner)->allows('addVersion', $document));
        $this->assertTrue(Gate::forUser($coResearcher)->allows('addVersion', $document));
        $this->assertFalse(Gate::forUser($viewer)->allows('addVersion', $document));
        $this->assertFalse(Gate::forUser($outsider)->allows('updateStatus', $document));
    }

    public function test_documents_from_other_projects_are_not_visible_in_scoped_query(): void
    {
        [$owner, $project, $category] = $this->projectFixture();
        $outsider = User::factory()->create();
        $outsiderProject = ResearchProject::create([
            'owner_id' => $outsider->id,
            'title' => 'Other Study',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        $visibleDocument = app(CreateDocumentAction::class)->handle($owner, $project, $category, [
            'title' => 'Visible Document',
        ]);
        $hiddenDocument = app(CreateDocumentAction::class)->handle($outsider, $outsiderProject, $category, [
            'title' => 'Hidden Document',
        ]);

        $visibleIds = Document::query()->visibleTo($owner)->pluck('id')->all();

        $this->assertContains($visibleDocument->id, $visibleIds);
        $this->assertNotContains($hiddenDocument->id, $visibleIds);
    }

    public function test_unauthorized_user_cannot_create_upload_or_change_status(): void
    {
        [$owner, $project, $category] = $this->projectFixture();
        $outsider = User::factory()->create();

        $document = app(CreateDocumentAction::class)->handle($owner, $project, $category, [
            'title' => 'Owner Document',
        ]);

        try {
            app(CreateDocumentAction::class)->handle($outsider, $project, $category, [
                'title' => 'Unauthorized Document',
            ]);
            $this->fail('Expected unauthorized document creation to fail.');
        } catch (AuthorizationException) {
            //
        }

        try {
            app(AddDocumentVersionAction::class)->handle($outsider, $document, new DocumentUploadData(
                fileName: 'unauthorized.pdf',
                mimeType: 'application/pdf',
                storageStatus: DocumentVersion::STORAGE_STATUS_FAKE,
            ));
            $this->fail('Expected unauthorized document version upload to fail.');
        } catch (AuthorizationException) {
            //
        }

        try {
            app(UpdateDocumentStatusAction::class)->handle($outsider, $document, Document::STATUS_APPROVED);
            $this->fail('Expected unauthorized status update to fail.');
        } catch (AuthorizationException) {
            //
        }

        $this->assertDatabaseCount('document_versions', 0);
        $this->assertSame(Document::STATUS_DRAFT, $document->fresh()->status);
    }

    public function test_super_admin_can_access_any_document(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $category] = $this->projectFixture();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $document = app(CreateDocumentAction::class)->handle($owner, $project, $category, [
            'title' => 'Super Admin Visible',
        ]);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('view', $document));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('updateStatus', $document));
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
            'title' => 'Authorization Study',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $category = DocumentCategory::where('slug', 'proposal')->firstOrFail();

        return [$owner, $project, $category];
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
