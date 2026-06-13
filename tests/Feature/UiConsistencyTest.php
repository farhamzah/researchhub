<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_templates_render_standard_header_cards_and_safe_actions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->adminUser('ui-template-admin@example.test');

        $this->actingAs($admin)
            ->get(route('admin.projects.templates.index'))
            ->assertOk()
            ->assertSee('data-ui="myriset-page-header"', false)
            ->assertSee('data-ui="myriset-section-card"', false)
            ->assertSee('data-ui="myriset-action-link"', false)
            ->assertSeeText('Buat Project dari Template')
            ->assertDontSee('token_hash')
            ->assertDontSee('drive_file_id')
            ->assertDontSee('/validation/survey/')
            ->assertDontSee('/supervision/review/');

        $this->actingAs($admin)
            ->get(route('admin.projects.templates.show', ['template' => 'pharmvr_development_evaluation']))
            ->assertOk()
            ->assertSee('data-ui="myriset-page-header"', false)
            ->assertSee('data-ui="myriset-section-card"', false)
            ->assertSeeText('Check answers')
            ->assertSee('for="title"', false)
            ->assertSee('for="description"', false)
            ->assertSee('focus-visible:outline', false);
    }

    public function test_project_journey_and_survey_builder_render_consistent_badges_and_empty_states(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->adminUser('ui-flow-admin@example.test');
        $project = ResearchProject::create([
            'owner_id' => $admin->id,
            'title' => 'UI Consistency Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($admin, $project, [
            'title' => 'UI Consistency Survey',
            'description' => 'Survey used for UI consistency checks.',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.projects.journey.show', ['researchProject' => $project]))
            ->assertOk()
            ->assertSee('data-ui="myriset-page-header"', false)
            ->assertSee('data-ui="myriset-section-card"', false)
            ->assertSee('data-ui="myriset-status-badge"', false)
            ->assertSeeText('Alur Riset')
            ->assertDontSee('token_hash')
            ->assertDontSee('drive_file_id');

        $this->actingAs($admin)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee('data-ui="myriset-page-header"', false)
            ->assertSee('data-ui="myriset-status-badge"', false)
            ->assertSee('data-ui="myriset-empty-state"', false)
            ->assertSeeText('Belum ada indikator')
            ->assertSeeText('Belum ada pertanyaan')
            ->assertSeeText('Preview belum tersedia')
            ->assertDontSee('token_hash')
            ->assertDontSee('response_token_hash')
            ->assertDontSee('/validation/survey/')
            ->assertDontSee('/supervision/review/');
    }

    public function test_ui_audit_document_exists_and_records_accessibility_scope(): void
    {
        $path = base_path('docs/MYRISET_UI_UX_AUDIT.md');

        $this->assertFileExists($path);
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString('Audit date: 2026-06-13', $contents);
        $this->assertStringContainsString('Accessibility Notes', $contents);
        $this->assertStringContainsString('Manual QA Checklist', $contents);
        $this->assertStringContainsString('P0: Must Fix Before Demo', $contents);
    }

    private function adminUser(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('admin');

        return $user;
    }
}
