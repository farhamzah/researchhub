<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'projects.view_any',
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.delete',
            'projects.manage_members',
            'projects.view_timeline',
            'projects.manage_timeline',
            'projects.manage_validators',
            'projects.manage_supervision',
            'expert_validators.view_any',
            'expert_validators.view',
            'expert_validators.create',
            'expert_validators.update',
            'expert_validators.delete',
            'documents.view_any',
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete',
            'documents.review',
            'review_links.view',
            'review_links.create',
            'review_links.revoke',
            'surveys.view_any',
            'surveys.view',
            'surveys.create',
            'surveys.update',
            'surveys.delete',
            'surveys.manage_responses',
            'surveys.view_respondent_identity',
            'surveys.export_responses',
            'surveys.manage_scoring',
            'surveys.manage_validation',
            'analysis.view',
            'analysis.run',
            'analysis.export',
            'drive_connections.view',
            'drive_connections.manage',
            'activity_logs.view',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $admin = Role::query()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $researcher = Role::query()->firstOrCreate([
            'name' => 'researcher',
            'guard_name' => 'web',
        ]);

        $adminPermissions = $permissions;

        $researcherPermissions = [
            'projects.view_any',
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.manage_members',
            'projects.view_timeline',
            'projects.manage_timeline',
            'projects.manage_validators',
            'projects.manage_supervision',
            'expert_validators.view_any',
            'expert_validators.view',
            'expert_validators.create',
            'expert_validators.update',
            'expert_validators.delete',
            'documents.view_any',
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete',
            'documents.review',
            'review_links.view',
            'review_links.create',
            'review_links.revoke',
            'surveys.view_any',
            'surveys.view',
            'surveys.create',
            'surveys.update',
            'surveys.delete',
            'surveys.manage_responses',
            'surveys.export_responses',
            'surveys.manage_scoring',
            'surveys.manage_validation',
            'analysis.view',
            'analysis.run',
            'analysis.export',
            'drive_connections.view',
            'drive_connections.manage',
        ];

        $superAdmin->syncPermissions($permissions);
        $admin->syncPermissions($adminPermissions);
        $researcher->syncPermissions($researcherPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
