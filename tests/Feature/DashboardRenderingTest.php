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
            ->assertSee('Research workspace for projects, documents, surveys, analysis, and academic drafts.')
            ->assertSee('Research Projects')
            ->assertSee('Recommended next steps')
            ->assertSee('Quick Actions')
            ->assertSee('Academic Research Workspace')
            ->assertSee('data-dashboard-card="hero"', false)
            ->assertSee('data-dashboard-card="stat"', false)
            ->assertSee('data-dashboard-card="next-steps"', false)
            ->assertSee('data-dashboard-card="quick-actions"', false)
            ->assertSee('Open Research Projects')
            ->assertSee('Open Documents')
            ->assertSee('Open Surveys')
            ->assertSee('Google Drive Settings')
            ->assertSee('Open a project to manage its timeline')
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
            ->assertSee('Quick Actions')
            ->assertSee('rh-action-tile');
    }

    private function adminUser(): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
