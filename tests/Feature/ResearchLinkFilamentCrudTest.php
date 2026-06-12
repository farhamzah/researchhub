<?php

namespace Tests\Feature;

use App\Filament\Resources\ResearchLinks\Pages\ManageResearchLinks;
use App\Models\ActivityLog;
use App\Models\ResearchLink;
use App\Models\ResearchProject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResearchLinkFilamentCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_open_and_delete_research_link_from_filament_resource(): void
    {
        [$admin, $project] = $this->projectFixture();

        Livewire::actingAs($admin)
            ->test(ManageResearchLinks::class)
            ->assertSee('No research links yet')
            ->assertSee('Save useful research websites such as journals, OJS pages, regulations, datasets, repositories, and learning resources.')
            ->callAction('create', [
                'research_project_id' => $project->id,
                'title' => 'Google Scholar',
                'url' => 'https://scholar.google.com/?token=do-not-log',
                'description' => 'Academic literature search.',
                'category' => ResearchLink::CATEGORY_REFERENCE,
                'thumbnail_url' => 'https://example.test/scholar.png',
                'favicon_url' => 'https://example.test/favicon.ico',
                'is_pinned' => true,
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertHasNoActionErrors();

        $link = ResearchLink::query()->where('title', 'Google Scholar')->firstOrFail();

        $this->assertSame($project->id, $link->research_project_id);
        $this->assertSame($admin->id, $link->created_by);
        $this->assertTrue($link->is_pinned);

        $createdLog = ActivityLog::query()->where('action', 'research_link.created')->firstOrFail();
        $this->assertSame('scholar.google.com', $createdLog->metadata['host']);
        $this->assertStringNotContainsString('do-not-log', json_encode($createdLog->metadata, JSON_THROW_ON_ERROR));

        Livewire::actingAs($admin)
            ->test(ManageResearchLinks::class)
            ->assertSee('Google Scholar')
            ->assertSee('Reference')
            ->assertSee('scholar.google.com')
            ->assertTableActionVisible('open', $link)
            ->assertTableActionShouldOpenUrlInNewTab('open', $link)
            ->assertTableActionHasUrl('open', 'https://scholar.google.com/?token=do-not-log', $link)
            ->assertTableActionVisible('edit', $link)
            ->assertTableActionVisible('delete', $link)
            ->callAction(TestAction::make('edit')->table($link), [
                'research_project_id' => $project->id,
                'title' => 'Semantic Scholar',
                'url' => 'https://www.semanticscholar.org',
                'description' => 'AI-assisted academic literature search.',
                'category' => ResearchLink::CATEGORY_AI_TOOL,
                'thumbnail_url' => null,
                'favicon_url' => null,
                'is_pinned' => false,
                'is_active' => true,
                'sort_order' => 2,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('research_links', [
            'id' => $link->id,
            'title' => 'Semantic Scholar',
            'category' => ResearchLink::CATEGORY_AI_TOOL,
            'is_pinned' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(ManageResearchLinks::class)
            ->callAction(TestAction::make('delete')->table($link))
            ->assertHasNoActionErrors();

        $this->assertSoftDeleted('research_links', ['id' => $link->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'research_link.updated']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'research_link.deleted']);
    }

    public function test_project_selection_is_scoped_and_unsafe_urls_are_rejected(): void
    {
        [$owner, $project] = $this->projectFixture('Owner Project', 'owner@example.test');
        $outsider = $this->adminUser('outsider@example.test');

        Livewire::actingAs($owner)
            ->test(ManageResearchLinks::class)
            ->assertSee('Owner Project');

        Livewire::actingAs($outsider)
            ->test(ManageResearchLinks::class)
            ->assertDontSee('Owner Project')
            ->callAction('create', [
                'research_project_id' => $project->id,
                'title' => 'Cross Scope Link',
                'url' => 'https://example.test',
                'category' => ResearchLink::CATEGORY_OTHER,
                'is_pinned' => false,
                'is_active' => true,
                'sort_order' => 0,
            ])
            ->assertHasActionErrors(['research_project_id']);

        Livewire::actingAs($outsider)
            ->test(ManageResearchLinks::class)
            ->callAction('create', [
                'research_project_id' => null,
                'title' => 'Unsafe Link',
                'url' => 'javascript:alert(1)',
                'category' => ResearchLink::CATEGORY_OTHER,
                'is_pinned' => false,
                'is_active' => true,
                'sort_order' => 0,
            ])
            ->assertHasActionErrors(['url']);

        $this->assertDatabaseMissing('research_links', ['title' => 'Cross Scope Link']);
        $this->assertDatabaseMissing('research_links', ['title' => 'Unsafe Link']);
    }

    public function test_research_links_navigation_item_is_rendered_in_admin_panel(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Research Resources')
            ->assertSee('Research Links');
    }

    private function adminUser(string $email = 'admin@example.test'): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create([
            'email' => $email,
        ]);
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @return array{0: User, 1: ResearchProject}
     */
    private function projectFixture(string $title = 'Research Link Project', string $email = 'admin@example.test'): array
    {
        $owner = $this->adminUser($email);
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => $title,
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        return [$owner, $project];
    }
}
