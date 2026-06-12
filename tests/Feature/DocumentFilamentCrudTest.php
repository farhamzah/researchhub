<?php

namespace Tests\Feature;

use App\Filament\Resources\Documents\Pages\ManageDocuments;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\ProjectMember;
use App\Models\ResearchProject;
use App\Models\User;
use Database\Seeders\DocumentCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentFilamentCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_delete_document_record_from_filament_resource(): void
    {
        [$owner, $project, $category] = $this->projectFixture();

        $this->actingAs($owner)
            ->get('/admin/documents')
            ->assertOk()
            ->assertSee('Create Document Record')
            ->assertSee('No research documents yet')
            ->assertSee('Create your first research document record');

        Livewire::actingAs($owner)
            ->test(ManageDocuments::class)
            ->callAction('create', [
                'project_id' => $project->id,
                'category_id' => $category->id,
                'title' => 'Proposal Metadata Record',
                'description' => 'Metadata only; no fake upload.',
                'status' => Document::STATUS_DRAFT,
                'visibility' => Document::VISIBILITY_PROJECT,
            ])
            ->assertHasNoActionErrors();

        $document = Document::query()->where('title', 'Proposal Metadata Record')->firstOrFail();

        $this->assertSame($project->id, $document->project_id);
        $this->assertSame($category->id, $document->category_id);
        $this->assertSame($owner->id, $document->owner_id);
        $this->assertNull($document->current_version_id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'document.created']);

        Livewire::actingAs($owner)
            ->test(ManageDocuments::class)
            ->assertSee('Proposal Metadata Record')
            ->assertSee('Project Members')
            ->assertTableActionVisible('edit', $document)
            ->assertTableActionVisible('delete', $document)
            ->assertTableActionVisible('reviewLinks', $document)
            ->callAction(TestAction::make('edit')->table($document), [
                'project_id' => $project->id,
                'category_id' => $category->id,
                'title' => 'Proposal Metadata Revised',
                'description' => 'Updated document metadata.',
                'status' => Document::STATUS_UNDER_REVIEW,
                'visibility' => Document::VISIBILITY_PRIVATE,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Proposal Metadata Revised',
            'status' => Document::STATUS_UNDER_REVIEW,
            'visibility' => Document::VISIBILITY_PRIVATE,
        ]);

        $this->assertDatabaseHas('activity_logs', ['action' => 'document.updated']);

        Livewire::actingAs($owner)
            ->test(ManageDocuments::class)
            ->callAction(TestAction::make('delete')->table($document))
            ->assertHasNoActionErrors();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'document.deleted']);
    }

    public function test_document_resource_scopes_project_assignment_and_table_visibility(): void
    {
        [$owner, $project, $category] = $this->projectFixture('Owner Study', 'owner@example.test');
        $outsider = $this->adminUser('outsider@example.test');
        $outsiderProject = ResearchProject::create([
            'owner_id' => $outsider->id,
            'title' => 'Outsider Study',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        Document::create([
            'project_id' => $project->id,
            'category_id' => $category->id,
            'owner_id' => $owner->id,
            'title' => 'Owner Private Document',
            'slug' => 'owner-private-document',
            'status' => Document::STATUS_DRAFT,
            'visibility' => Document::VISIBILITY_PRIVATE,
        ]);

        Livewire::actingAs($outsider)
            ->test(ManageDocuments::class)
            ->assertDontSee('Owner Private Document')
            ->callAction('create', [
                'project_id' => $project->id,
                'category_id' => $category->id,
                'title' => 'Cross Scope Document',
                'description' => null,
                'status' => Document::STATUS_DRAFT,
                'visibility' => Document::VISIBILITY_PRIVATE,
            ])
            ->assertHasActionErrors(['project_id']);

        Livewire::actingAs($outsider)
            ->test(ManageDocuments::class)
            ->callAction('create', [
                'project_id' => $outsiderProject->id,
                'category_id' => $category->id,
                'title' => 'Outsider Allowed Document',
                'description' => null,
                'status' => Document::STATUS_DRAFT,
                'visibility' => Document::VISIBILITY_PRIVATE,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('documents', ['title' => 'Cross Scope Document']);
        $this->assertDatabaseHas('documents', ['title' => 'Outsider Allowed Document']);
    }

    public function test_viewer_project_member_cannot_edit_or_delete_document(): void
    {
        [$owner, $project, $category] = $this->projectFixture();
        $viewer = $this->adminUser('viewer@example.test');

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $viewer->id,
            'role' => ProjectMember::ROLE_VIEWER,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);

        $document = Document::create([
            'project_id' => $project->id,
            'category_id' => $category->id,
            'owner_id' => $owner->id,
            'title' => 'Viewer Visible Document',
            'slug' => 'viewer-visible-document',
            'status' => Document::STATUS_DRAFT,
            'visibility' => Document::VISIBILITY_PROJECT,
        ]);

        Livewire::actingAs($viewer)
            ->test(ManageDocuments::class)
            ->assertSee('Viewer Visible Document')
            ->assertTableActionHidden('edit', $document)
            ->assertTableActionHidden('delete', $document)
            ->assertTableActionHidden('reviewLinks', $document);
    }

    private function adminUser(string $email = 'admin@example.test'): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: DocumentCategory}
     */
    private function projectFixture(string $title = 'Document CRUD Study', string $email = 'admin@example.test'): array
    {
        $this->seed(DocumentCategorySeeder::class);

        $owner = $this->adminUser($email);
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => $title,
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $category = DocumentCategory::where('slug', 'proposal')->firstOrFail();

        return [$owner, $project, $category];
    }
}
