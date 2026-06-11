<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_legacy_url_requires_authentication(): void
    {
        $this->get('/admin/dashboard')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_home_renders_custom_researchhub_dashboard(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Welcome to ResearchHub')
            ->assertSee('Manage your research projects, documents, surveys, analysis, and academic drafts in one place.')
            ->assertSee('Research Projects')
            ->assertSee('Recommended next steps')
            ->assertSee('Quick Actions')
            ->assertSee('Open Documents')
            ->assertSee('Open Surveys')
            ->assertDontSee('filamentphp.com');
    }

    public function test_dashboard_legacy_url_redirects_to_custom_dashboard(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertRedirect('/admin');

        $this->actingAs($user)
            ->followingRedirects()
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Welcome to ResearchHub')
            ->assertSee('Recommended next steps')
            ->assertSee('Quick Actions');
    }

    private function adminUser(): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
