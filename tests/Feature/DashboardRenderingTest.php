<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRenderingTest extends TestCase
{
    use RefreshDatabase;

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
}
