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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentRevisionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_can_store_revision_metadata_and_render_admin_list_safely(): void
    {
        [$owner, $project, $category] = $this->projectFixture();

        $document = Document::create([
            'project_id' => $project->id,
            'category_id' => $category->id,
            'owner_id' => $owner->id,
            'title' => 'BAB III Metodologi Penelitian',
            'slug' => 'bab-iii-metodologi-penelitian',
            'status' => Document::STATUS_REVISION_REQUIRED,
            'visibility' => Document::VISIBILITY_PROJECT,
            'document_type' => Document::TYPE_CHAPTER_3,
            'version_label' => 'v02',
            'version_number' => 2,
            'is_current' => true,
            'reviewer_name' => 'Demo Supervisor',
            'reviewed_at' => now()->subDay(),
            'revision_due_date' => today()->addDays(7),
            'revision_summary' => 'Metodologi perlu memperjelas desain uji coba.',
            'next_action' => 'Lengkapi referensi metodologi dan kirim ulang.',
        ]);

        $this->assertSame('Revision Needed', $document->statusLabel());
        $this->assertSame('BAB III', $document->documentTypeLabel());
        $this->assertSame('v02', $document->versionDisplay());
        $this->assertTrue($document->needsRevision());

        Livewire::actingAs($owner)
            ->test(ManageDocuments::class)
            ->assertSee('BAB III Metodologi Penelitian')
            ->assertSee('Revision Needed')
            ->assertSee('BAB III')
            ->assertSee('v02')
            ->assertSee('Current')
            ->assertSee('Lengkapi referensi metodologi')
            ->assertDontSee('token_hash')
            ->assertDontSee('drive_file_id')
            ->assertDontSee('C:\\');
    }

    public function test_filament_create_action_accepts_academic_revision_metadata(): void
    {
        [$owner, $project, $category] = $this->projectFixture('Revision Create Study', 'revision-create@example.test');

        Livewire::actingAs($owner)
            ->test(ManageDocuments::class)
            ->callAction('create', [
                'project_id' => $project->id,
                'category_id' => $category->id,
                'title' => 'Proposal Disertasi PharmVR',
                'description' => 'Metadata only.',
                'status' => Document::STATUS_UNDER_REVIEW,
                'visibility' => Document::VISIBILITY_PROJECT,
                'document_type' => Document::TYPE_PROPOSAL,
                'version_label' => 'v02',
                'version_number' => 2,
                'is_current' => true,
                'reviewer_name' => 'Demo Supervisor',
                'reviewed_at' => today()->toDateString(),
                'revision_due_date' => today()->addDays(5)->toDateString(),
                'revision_summary' => 'Proposal sedang direview.',
                'next_action' => 'Tunggu masukan pembimbing.',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('documents', [
            'project_id' => $project->id,
            'title' => 'Proposal Disertasi PharmVR',
            'document_type' => Document::TYPE_PROPOSAL,
            'version_label' => 'v02',
            'version_number' => 2,
            'status' => Document::STATUS_UNDER_REVIEW,
            'next_action' => 'Tunggu masukan pembimbing.',
        ]);
    }

    public function test_unauthorized_user_cannot_see_revision_metadata_from_another_project(): void
    {
        [$owner, $project, $category] = $this->projectFixture('Private Revision Study', 'private-revision@example.test');
        $outsider = $this->adminUser('revision-outsider@example.test');

        Document::create([
            'project_id' => $project->id,
            'category_id' => $category->id,
            'owner_id' => $owner->id,
            'title' => 'Private Revision Document',
            'slug' => 'private-revision-document',
            'status' => Document::STATUS_REVISION_REQUIRED,
            'visibility' => Document::VISIBILITY_PROJECT,
            'document_type' => Document::TYPE_CHAPTER_3,
            'version_label' => 'v02',
            'next_action' => 'Private next action should not be visible.',
        ]);

        ProjectMember::query()->where('project_id', $project->id)->delete();

        Livewire::actingAs($outsider)
            ->test(ManageDocuments::class)
            ->assertDontSee('Private Revision Document')
            ->assertDontSee('Private next action should not be visible.');
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
    private function projectFixture(string $title = 'Document Revision Study', string $email = 'admin@example.test'): array
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
