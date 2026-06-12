<?php

namespace Tests\Feature;

use App\Models\ProjectMember;
use App\Models\ResearchLink;
use App\Models\ResearchProject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchLinkPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_research_links_are_visible_to_members_but_managed_by_project_editors(): void
    {
        [$owner, $project] = $this->projectFixture();
        $viewer = User::factory()->create();
        $coResearcher = User::factory()->create();
        $outsider = User::factory()->create();

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $viewer->id,
            'role' => ProjectMember::ROLE_VIEWER,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $coResearcher->id,
            'role' => ProjectMember::ROLE_CO_RESEARCHER,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);

        $link = ResearchLink::create([
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'title' => 'OJS Portal',
            'url' => 'https://journal.example.test/ojs',
        ]);

        $this->assertTrue($owner->can('view', $link));
        $this->assertTrue($owner->can('update', $link));
        $this->assertTrue($viewer->can('view', $link));
        $this->assertFalse($viewer->can('update', $link));
        $this->assertTrue($coResearcher->can('update', $link));
        $this->assertFalse($outsider->can('view', $link));
        $this->assertFalse($outsider->can('update', $link));
    }

    public function test_global_links_are_managed_only_by_creator_or_super_admin(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $creator = User::factory()->create();
        $other = User::factory()->create();
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $link = ResearchLink::create([
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
            'title' => 'Google Scholar',
            'url' => 'https://scholar.google.com',
        ]);

        $this->assertTrue($creator->can('view', $link));
        $this->assertTrue($creator->can('update', $link));
        $this->assertFalse($other->can('view', $link));
        $this->assertFalse($other->can('update', $link));
        $this->assertTrue($superAdmin->can('view', $link));
        $this->assertTrue($superAdmin->can('update', $link));
    }

    /**
     * @return array{0: User, 1: ResearchProject}
     */
    private function projectFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Resource Link Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        return [$owner, $project];
    }
}
