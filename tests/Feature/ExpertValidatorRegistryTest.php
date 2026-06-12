<?php

namespace Tests\Feature;

use App\Filament\Resources\ExpertValidators\Pages\ManageExpertValidators;
use App\Filament\Resources\Projects\Pages\ManageResearchProjects;
use App\Models\ActivityLog;
use App\Models\ExpertValidator;
use App\Models\ExpertValidatorProject;
use App\Models\ResearchProject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpertValidatorRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_delete_private_expert_validator(): void
    {
        $admin = $this->adminUser();

        Livewire::actingAs($admin)
            ->test(ManageExpertValidators::class)
            ->assertSee('No expert validators yet')
            ->assertSee('Create a reusable expert validator profile before assigning validators to research projects.')
            ->callAction('create', [
                'name' => 'Dr. Content Expert',
                'email' => 'content@example.test',
                'phone' => '+6200000001',
                'institution' => 'Research University',
                'position' => 'Senior Lecturer',
                'expertise_areas' => ['Instrument Validation', 'Qualitative Methods'],
                'notes' => 'Internal reviewer notes must not enter activity metadata.',
                'is_active' => true,
                'is_global' => true,
            ])
            ->assertHasNoActionErrors();

        $validator = ExpertValidator::query()->where('email', 'content@example.test')->firstOrFail();

        $this->assertSame($admin->id, $validator->created_by);
        $this->assertFalse($validator->is_global);
        $this->assertSame(['Instrument Validation', 'Qualitative Methods'], $validator->expertise_areas);

        $createdLog = ActivityLog::query()->where('action', 'expert_validator.created')->firstOrFail();
        $this->assertSame($validator->id, $createdLog->metadata['expert_validator_id']);
        $this->assertStringNotContainsString('content@example.test', json_encode($createdLog->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Internal reviewer notes', json_encode($createdLog->metadata, JSON_THROW_ON_ERROR));

        Livewire::actingAs($admin)
            ->test(ManageExpertValidators::class)
            ->assertSee('Dr. Content Expert')
            ->assertSee('Research University')
            ->assertTableActionVisible('edit', $validator)
            ->assertTableActionVisible('delete', $validator)
            ->callAction(TestAction::make('edit')->table($validator), [
                'name' => 'Dr. Content Expert Updated',
                'email' => 'content.updated@example.test',
                'phone' => '+6200000002',
                'institution' => 'Research University',
                'position' => 'Associate Professor',
                'expertise_areas' => ['Psychometrics'],
                'notes' => 'Updated internal notes.',
                'is_active' => true,
                'is_global' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('expert_validators', [
            'id' => $validator->id,
            'name' => 'Dr. Content Expert Updated',
            'is_global' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(ManageExpertValidators::class)
            ->callAction(TestAction::make('delete')->table($validator))
            ->assertHasNoActionErrors();

        $this->assertSoftDeleted('expert_validators', ['id' => $validator->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'expert_validator.updated']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'expert_validator.deleted']);
    }

    public function test_expert_validator_visibility_is_scoped_to_owner_and_active_global_records(): void
    {
        $owner = $this->adminUser('owner@example.test');
        $outsider = $this->adminUser('outsider@example.test');
        $superAdmin = $this->superAdminUser();

        $ownValidator = ExpertValidator::create([
            'created_by' => $owner->id,
            'name' => 'Owner Private Validator',
            'is_active' => true,
            'is_global' => false,
        ]);

        $outsiderValidator = ExpertValidator::create([
            'created_by' => $outsider->id,
            'name' => 'Outsider Private Validator',
            'is_active' => true,
            'is_global' => false,
        ]);

        $globalValidator = ExpertValidator::create([
            'created_by' => $superAdmin->id,
            'name' => 'Global Active Validator',
            'is_active' => true,
            'is_global' => true,
        ]);

        $inactiveGlobalValidator = ExpertValidator::create([
            'created_by' => $superAdmin->id,
            'name' => 'Global Inactive Validator',
            'is_active' => false,
            'is_global' => true,
        ]);

        Livewire::actingAs($owner)
            ->test(ManageExpertValidators::class)
            ->assertSee($ownValidator->name)
            ->assertSee($globalValidator->name)
            ->assertDontSee($outsiderValidator->name)
            ->assertDontSee($inactiveGlobalValidator->name);

        Livewire::actingAs($superAdmin)
            ->test(ManageExpertValidators::class)
            ->assertSee($ownValidator->name)
            ->assertSee($outsiderValidator->name)
            ->assertSee($globalValidator->name)
            ->assertSee($inactiveGlobalValidator->name);
    }

    public function test_project_owner_can_assign_visible_validator_and_reuse_validator_across_projects(): void
    {
        $owner = $this->adminUser('owner@example.test');
        $otherUser = $this->adminUser('other@example.test');

        $firstProject = $this->project($owner, 'Validation Project A');
        $secondProject = $this->project($owner, 'Validation Project B');

        $visibleValidator = ExpertValidator::create([
            'created_by' => $owner->id,
            'name' => 'Reusable Validator',
            'is_active' => true,
            'is_global' => false,
        ]);

        $otherPrivateValidator = ExpertValidator::create([
            'created_by' => $otherUser->id,
            'name' => 'Other Private Validator',
            'is_active' => true,
            'is_global' => false,
        ]);

        $this->actingAs($owner)
            ->get(route('admin.projects.validators.index', ['researchProject' => $firstProject]))
            ->assertOk()
            ->assertSee('Project Validators')
            ->assertSee('Reusable Validator')
            ->assertDontSee('Other Private Validator');

        $this->actingAs($owner)
            ->post(route('admin.projects.validators.store', ['researchProject' => $firstProject]), [
                'expert_validator_id' => $visibleValidator->id,
                'role' => ExpertValidatorProject::ROLE_CONTENT,
                'expertise_scope' => 'Content validity review',
                'status' => ExpertValidatorProject::STATUS_INVITED,
                'notes' => 'Project-only internal notes.',
            ])
            ->assertRedirect(route('admin.projects.validators.index', ['researchProject' => $firstProject]));

        $this->actingAs($owner)
            ->post(route('admin.projects.validators.store', ['researchProject' => $secondProject]), [
                'expert_validator_id' => $visibleValidator->id,
                'role' => ExpertValidatorProject::ROLE_METHODS,
                'expertise_scope' => 'Methodology review',
                'status' => ExpertValidatorProject::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.projects.validators.index', ['researchProject' => $secondProject]));

        $this->assertDatabaseHas('expert_validator_project', [
            'research_project_id' => $firstProject->id,
            'expert_validator_id' => $visibleValidator->id,
            'role' => ExpertValidatorProject::ROLE_CONTENT,
        ]);
        $this->assertDatabaseHas('expert_validator_project', [
            'research_project_id' => $secondProject->id,
            'expert_validator_id' => $visibleValidator->id,
            'role' => ExpertValidatorProject::ROLE_METHODS,
        ]);

        $assignment = ExpertValidatorProject::query()
            ->where('research_project_id', $firstProject->id)
            ->where('expert_validator_id', $visibleValidator->id)
            ->firstOrFail();

        $this->actingAs($owner)
            ->put(route('admin.projects.validators.update', ['researchProject' => $firstProject, 'assignment' => $assignment]), [
                'role' => ExpertValidatorProject::ROLE_INSTRUMENT,
                'expertise_scope' => 'Instrument blueprint review',
                'status' => ExpertValidatorProject::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.projects.validators.index', ['researchProject' => $firstProject]));

        $this->assertDatabaseHas('expert_validator_project', [
            'id' => $assignment->id,
            'role' => ExpertValidatorProject::ROLE_INSTRUMENT,
            'status' => ExpertValidatorProject::STATUS_ACTIVE,
        ]);

        $this->actingAs($owner)
            ->delete(route('admin.projects.validators.destroy', ['researchProject' => $firstProject, 'assignment' => $assignment]))
            ->assertRedirect(route('admin.projects.validators.index', ['researchProject' => $firstProject]));

        $this->assertDatabaseMissing('expert_validator_project', ['id' => $assignment->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'expert_validator.assigned_to_project']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'expert_validator.project_assignment_updated']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'expert_validator.detached_from_project']);

        $assignmentLog = ActivityLog::query()->where('action', 'expert_validator.assigned_to_project')->firstOrFail();
        $this->assertStringNotContainsString('Project-only internal notes', json_encode($assignmentLog->metadata, JSON_THROW_ON_ERROR));

        $this->actingAs($owner)
            ->from(route('admin.projects.validators.index', ['researchProject' => $firstProject]))
            ->post(route('admin.projects.validators.store', ['researchProject' => $firstProject]), [
                'expert_validator_id' => $otherPrivateValidator->id,
                'role' => ExpertValidatorProject::ROLE_CONTENT,
                'status' => ExpertValidatorProject::STATUS_INVITED,
            ])
            ->assertSessionHasErrors('expert_validator_id');
    }

    public function test_project_resource_exposes_validator_assignment_action(): void
    {
        $owner = $this->adminUser('owner@example.test');
        $project = $this->project($owner, 'Action Project');

        Livewire::actingAs($owner)
            ->test(ManageResearchProjects::class)
            ->assertTableActionVisible('validators', $project)
            ->assertTableActionHasUrl('validators', route('admin.projects.validators.index', ['researchProject' => $project]), $project);
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

    private function superAdminUser(string $email = 'super@example.test'): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create([
            'email' => $email,
        ]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function project(User $owner, string $title): ResearchProject
    {
        return ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => $title,
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
    }
}
