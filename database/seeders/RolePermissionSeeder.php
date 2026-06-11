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
            'projects.create',
            'projects.view',
            'projects.update',
            'projects.delete',
            'projects.manage_members',
            'activity_logs.view',
            'surveys.view_respondent_identity',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $admin = Role::findOrCreate('admin', 'web');
        $researcher = Role::findOrCreate('researcher', 'web');

        $superAdmin->syncPermissions($permissions);
        $admin->syncPermissions([
            'projects.view_any',
            'projects.create',
            'projects.view',
            'projects.update',
            'projects.delete',
            'projects.manage_members',
            'activity_logs.view',
            'surveys.view_respondent_identity',
        ]);
        $researcher->syncPermissions([
            'projects.create',
            'projects.view',
            'projects.update',
            'projects.manage_members',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
