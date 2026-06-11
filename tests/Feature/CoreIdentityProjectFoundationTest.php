<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ProjectMember;
use App\Models\ResearchProject;
use App\Models\Role;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreIdentityProjectFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model_and_database_use_uuid_primary_keys(): void
    {
        $user = User::factory()->create();

        $this->assertIsString($user->id);
        $this->assertTrue(Str::isUuid($user->id));
        $this->assertFalse($user->incrementing);
        $this->assertSame('string', $user->getKeyType());
    }

    public function test_role_permission_seeder_creates_uuid_roles_and_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $role = Role::query()->where('name', 'super_admin')->firstOrFail();

        $this->assertTrue(Str::isUuid($role->id));
        $this->assertDatabaseHas('permissions', ['name' => 'projects.manage_members']);
        $this->assertTrue($role->hasPermissionTo('projects.manage_members'));
    }

    public function test_project_policy_allows_owner_and_active_member_but_blocks_outsider(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Dissertation Study',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectMember::ROLE_VIEWER,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $project));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $project));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $project));

        $this->assertTrue(Gate::forUser($member)->allows('view', $project));
        $this->assertFalse(Gate::forUser($member)->allows('update', $project));
        $this->assertFalse(Gate::forUser($member)->allows('manageMembers', $project));

        $this->assertFalse(Gate::forUser($outsider)->allows('view', $project));
        $this->assertFalse(Gate::forUser($outsider)->allows('update', $project));
    }

    public function test_project_policy_allows_super_admin_to_access_any_project(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = User::factory()->create();
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Protected Study',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('view', $project));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', $project));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('delete', $project));
    }

    public function test_activity_logger_records_project_actions(): void
    {
        $user = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $user->id,
            'title' => 'Audit Study',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        $log = app(ActivityLogger::class)->log(
            action: 'project.created',
            user: $user,
            project: $project,
            subject: $project,
            metadata: ['source' => 'test'],
        );

        $this->assertInstanceOf(ActivityLog::class, $log);
        $this->assertTrue(Str::isUuid($log->id));
        $this->assertDatabaseHas('activity_logs', [
            'id' => $log->id,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'action' => 'project.created',
        ]);
    }
}
