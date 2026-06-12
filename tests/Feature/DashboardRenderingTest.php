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
            ->assertSee('Welcome to MyRiset')
            ->assertDontSee('Welcome to ResearchHub')
            ->assertDontSee('<h1 class="fi-header-heading', false)
            ->assertSee('Platform manajemen riset, validasi ahli, bimbingan, dan laporan akademik.')
            ->assertSee('Research Projects')
            ->assertSee('Active Projects')
            ->assertSee('Timeline Focus')
            ->assertSee('Recent Documents')
            ->assertSee('Recent Surveys')
            ->assertSee('Pinned Research Links')
            ->assertSee('Quick Actions')
            ->assertSee('Academic Research Command Center')
            ->assertSee('data-dashboard-card="hero"', false)
            ->assertSee('data-dashboard-card="stat"', false)
            ->assertSee('data-dashboard-card="active-projects"', false)
            ->assertSee('data-dashboard-card="timeline-focus"', false)
            ->assertSee('data-dashboard-card="recent-documents"', false)
            ->assertSee('data-dashboard-card="recent-surveys"', false)
            ->assertSee('data-dashboard-card="pinned-research-links"', false)
            ->assertSee('data-dashboard-card="quick-actions"', false)
            ->assertSee('--default-theme-mode: light', false)
            ->assertSee('background: #f8fafc;', false)
            ->assertDontSee('linear-gradient(135deg, #0f172a', false)
            ->assertSee('Open Projects')
            ->assertSee('Open Documents')
            ->assertSee('Open Surveys')
            ->assertSee('Open Research Links')
            ->assertSee('Google Drive Settings')
            ->assertSee('No projects yet. Create your first research project')
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
            ->assertSee('Welcome to MyRiset')
            ->assertDontSee('Welcome to ResearchHub')
            ->assertSee('Timeline Focus')
            ->assertSee('Quick Actions')
            ->assertSee('rh-action-tile');
    }

    public function test_admin_panel_resource_pages_use_global_light_academic_theme(): void
    {
        $user = $this->adminUser();

        foreach ([
            '/admin',
            '/admin/documents',
            '/admin/projects/research-projects',
            '/admin/surveys',
            '/admin/research-links',
            '/admin/settings/google-drive',
        ] as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertOk()
                ->assertSee('id="researchhub-panel-light-theme"', false)
                ->assertSee('--default-theme-mode: light', false)
                ->assertSee('color-scheme: light', false)
                ->assertSee('.fi-page-heading', false)
                ->assertSee('color: #0f172a !important;', false);
        }
    }

    private function adminUser(): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
