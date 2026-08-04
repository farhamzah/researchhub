<?php

namespace Tests\Feature;

use App\Filament\Resources\Surveys\Pages\ManageSurveys;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SurveyFilamentCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_access_existing_survey_actions_from_filament_resource(): void
    {
        [$admin, $project] = $this->projectFixture();

        Livewire::actingAs($admin)
            ->test(ManageSurveys::class)
            ->assertSee('No surveys yet')
            ->assertSee('Create your first research survey to collect responses, evaluate instruments, and generate descriptive analysis.')
            ->callAction('create', [
                'project_id' => $project->id,
                'title' => 'Manual Browser Survey',
                'description' => 'Created from the Filament survey resource.',
                'status' => Survey::STATUS_DRAFT,
                'identity_mode' => Survey::IDENTITY_HIDDEN,
                'is_public' => false,
            ])
            ->assertHasNoActionErrors();

        $survey = Survey::query()
            ->where('title', 'Manual Browser Survey')
            ->firstOrFail();

        $this->assertSame($project->id, $survey->project_id);
        $this->assertSame($admin->id, $survey->created_by);
        $this->assertSame(Survey::STATUS_DRAFT, $survey->status);
        $this->assertFalse($survey->is_public);

        Livewire::actingAs($admin)
            ->test(ManageSurveys::class)
            ->assertSee('Manual Browser Survey')
            ->assertTableActionVisible('edit', $survey)
            ->assertTableActionVisible('builder', $survey)
            ->assertTableActionVisible('responses', $survey)
            ->assertTableActionVisible('analysis', $survey)
            ->assertTableActionVisible('scoring', $survey)
            ->assertTableActionHasUrl('builder', route('admin.surveys.builder.index', ['survey' => $survey]), $survey)
            ->assertTableActionHasUrl('responses', route('admin.surveys.responses.index', ['survey' => $survey]), $survey)
            ->assertTableActionHasUrl('analysis', route('admin.surveys.analysis.index', ['survey' => $survey]), $survey)
            ->assertTableActionHasUrl('scoring', route('admin.surveys.scoring.index', ['survey' => $survey]), $survey)
            ->callAction(TestAction::make('edit')->table($survey), [
                'project_id' => $project->id,
                'title' => 'Manual Browser Survey Updated',
                'description' => 'Updated from the Filament survey resource.',
                'status' => Survey::STATUS_PUBLISHED,
                'identity_mode' => Survey::IDENTITY_ANONYMOUS,
                'is_public' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('surveys', [
            'id' => $survey->id,
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title' => 'Manual Browser Survey Updated',
            'status' => Survey::STATUS_PUBLISHED,
            'identity_mode' => Survey::IDENTITY_ANONYMOUS,
            'is_public' => true,
        ]);
    }

    public function test_survey_create_rejects_project_assignment_outside_user_management_scope(): void
    {
        [$owner, $project] = $this->projectFixture('Owner Project', 'owner@example.test');
        $outsider = $this->adminUser('outsider@example.test');

        Livewire::actingAs($owner)
            ->test(ManageSurveys::class)
            ->assertSee('Owner Project');

        Livewire::actingAs($outsider)
            ->test(ManageSurveys::class)
            ->assertDontSee('Owner Project')
            ->callAction('create', [
                'project_id' => $project->id,
                'title' => 'Cross Scope Survey',
                'status' => Survey::STATUS_DRAFT,
                'identity_mode' => Survey::IDENTITY_HIDDEN,
                'is_public' => false,
            ])
            ->assertHasActionErrors(['project_id']);

        $this->assertDatabaseMissing('surveys', [
            'title' => 'Cross Scope Survey',
        ]);
    }

    public function test_dashboard_navigation_group_is_prioritized_above_main_resources(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeInOrder([
                'Workspace',
                'Dashboard',
                'Tata Kelola Riset',
                'Validator Ahli',
                'Referensi Riset',
                'Link Riset',
                'Project',
                'Project Riset',
                'Dokumen Riset',
                'Dokumen',
                'Survey &amp; Analisis',
                'Survey',
                'Integrasi',
                'Google Drive',
            ], false);
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
    private function projectFixture(string $title = 'Survey Project', string $email = 'admin@example.test'): array
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
