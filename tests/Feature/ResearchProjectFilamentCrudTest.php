<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\ManageResearchProjects;
use App\Models\ResearchProject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResearchProjectFilamentCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_open_project_timeline_from_filament_resource(): void
    {
        $admin = $this->adminUser();

        Livewire::actingAs($admin)
            ->test(ManageResearchProjects::class)
            ->assertSee('No research projects yet')
            ->assertSee('Create your first research project to organize documents, surveys, analysis, and timeline milestones.')
            ->callAction('create', [
                'title' => 'Manual Timeline Test',
                'description' => 'Created from the Filament project resource.',
                'status' => ResearchProject::STATUS_ACTIVE,
                'started_at' => '2026-06-01',
                'target_finished_at' => '2026-12-31',
            ])
            ->assertHasNoActionErrors();

        $project = ResearchProject::query()
            ->where('title', 'Manual Timeline Test')
            ->firstOrFail();

        $this->assertSame($admin->id, $project->owner_id);
        $this->assertSame(ResearchProject::STATUS_ACTIVE, $project->status);

        Livewire::actingAs($admin)
            ->test(ManageResearchProjects::class)
            ->assertSee('Manual Timeline Test')
            ->assertTableActionVisible('edit', $project)
            ->assertTableActionVisible('timeline', $project)
            ->assertTableActionHasUrl('timeline', route('admin.projects.timeline.index', ['researchProject' => $project]), $project)
            ->callAction(TestAction::make('edit')->table($project), [
                'title' => 'Manual Timeline Test Updated',
                'description' => 'Updated from the Filament project resource.',
                'status' => ResearchProject::STATUS_PAUSED,
                'started_at' => '2026-06-01',
                'target_finished_at' => '2027-01-15',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('research_projects', [
            'id' => $project->id,
            'owner_id' => $admin->id,
            'title' => 'Manual Timeline Test Updated',
            'status' => ResearchProject::STATUS_PAUSED,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.projects.timeline.index', ['researchProject' => $project]))
            ->assertOk()
            ->assertSee('Project Timeline')
            ->assertSee('Manual Timeline Test Updated');
    }

    public function test_project_resource_remains_scoped_to_visible_projects(): void
    {
        [$owner, $project] = $this->projectFixture('Owner Project');
        $outsider = $this->adminUser('outsider@example.test');

        Livewire::actingAs($owner)
            ->test(ManageResearchProjects::class)
            ->assertSee('Owner Project')
            ->assertTableActionVisible('edit', $project)
            ->assertTableActionVisible('timeline', $project);

        Livewire::actingAs($outsider)
            ->test(ManageResearchProjects::class)
            ->assertDontSee('Owner Project');
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
    private function projectFixture(string $title): array
    {
        $owner = $this->adminUser('owner@example.test');
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => $title,
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        return [$owner, $project];
    }
}
