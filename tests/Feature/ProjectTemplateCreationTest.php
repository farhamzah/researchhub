<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Models\ResearchLink;
use App\Models\ResearchProject;
use App\Models\ReviewLink;
use App\Models\SupervisionReviewLink;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyValidationAssignment;
use App\Models\User;
use App\Modules\Projects\Services\ProjectResearchJourneyService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTemplateCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_project_templates(): void
    {
        $this->get(route('admin.projects.templates.index'))
            ->assertRedirect('/admin/login');

        $this->post(route('admin.projects.templates.store', ['template' => 'dissertation_thesis']), [
            'title' => 'Unauthorized Template Project',
        ])->assertRedirect('/admin/login');
    }

    public function test_authenticated_user_can_create_project_from_dissertation_template(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = $this->adminUser('template-owner@example.test');

        $this->actingAs($user)
            ->get(route('admin.projects.templates.index'))
            ->assertOk()
            ->assertSeeText('Buat Project dari Template')
            ->assertSeeText('Disertasi / Tesis')
            ->assertSeeText('PharmVR Development & Evaluation')
            ->assertDontSee('token_hash')
            ->assertDontSee('drive_file_id');

        $this->actingAs($user)
            ->get(route('admin.projects.templates.show', ['template' => 'dissertation_thesis']))
            ->assertOk()
            ->assertSeeText('Project title')
            ->assertSeeText('Proposal Penelitian')
            ->assertSeeText('Susun rumusan masalah dan tujuan penelitian');

        $response = $this->actingAs($user)
            ->post(route('admin.projects.templates.store', ['template' => 'dissertation_thesis']), [
                'title' => 'Template Disertasi Baru',
                'description' => 'Starter project dari template.',
                'started_at' => today()->toDateString(),
                'target_finished_at' => today()->addDays(180)->toDateString(),
                'include_documents' => '1',
                'include_survey' => '1',
                'include_research_links' => '1',
            ]);

        $project = ResearchProject::query()->where('title', 'Template Disertasi Baru')->firstOrFail();

        $response
            ->assertRedirect(route('admin.projects.journey.show', ['researchProject' => $project]))
            ->assertSessionHas('status', 'Project berhasil dibuat dari template. Ikuti alur riset untuk melanjutkan.');

        $this->assertSame($user->id, $project->owner_id);
        $this->assertSame(ResearchProject::STATUS_ACTIVE, $project->status);
        $this->assertSame(7, ProjectMilestone::query()->where('research_project_id', $project->id)->count());
        $this->assertSame(7, ProjectTimelineTask::query()->where('research_project_id', $project->id)->count());
        $this->assertSame(6, Document::query()->where('project_id', $project->id)->count());
        $this->assertSame(0, Survey::query()->where('project_id', $project->id)->count());
        $this->assertSame(1, ResearchLink::query()->where('research_project_id', $project->id)->count());

        $this->assertDatabaseHas('documents', [
            'project_id' => $project->id,
            'title' => 'BAB III Metodologi Penelitian',
            'status' => Document::STATUS_DRAFT,
            'document_type' => Document::TYPE_CHAPTER_3,
            'version_label' => 'v01',
            'version_number' => 1,
            'is_current' => true,
            'next_action' => 'Sesuaikan dokumen dengan kebutuhan project.',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'project_id' => $project->id,
            'action' => 'project_template.applied',
        ]);
    }

    public function test_pharmvr_template_creates_starter_survey_and_safe_journey_dashboard_state(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = $this->adminUser('pharmvr-template@example.test');

        $this->actingAs($user)
            ->post(route('admin.projects.templates.store', ['template' => 'pharmvr_development_evaluation']), [
                'title' => 'Template PharmVR Baru',
                'description' => null,
                'include_documents' => '1',
                'include_survey' => '1',
                'include_research_links' => '1',
            ])
            ->assertRedirect();

        $project = ResearchProject::query()->where('title', 'Template PharmVR Baru')->firstOrFail();
        $survey = Survey::query()->where('project_id', $project->id)->firstOrFail();
        $journey = app(ProjectResearchJourneyService::class)->build($project->fresh());
        $steps = $journey['steps']->keyBy('key');

        $this->assertSame('Angket Evaluasi Pembelajaran PharmVR', $survey->title);
        $this->assertSame(Survey::STATUS_DRAFT, $survey->status);
        $this->assertSame(Survey::INSTRUMENT_ANALYSIS_STUDENT, $survey->instrument_type);
        $this->assertSame('Pengantar Kuesioner Analisis Kebutuhan PharmVR', $survey->intro_title);
        $this->assertSame('10-15 menit', $survey->estimated_duration);
        $this->assertTrue($survey->require_consent_before_start);
        $this->assertFalse((bool) $survey->is_public);
        $this->assertSame(4, SurveyQuestion::query()->where('survey_id', $survey->id)->count());
        $this->assertSame(7, Document::query()->where('project_id', $project->id)->count());
        $this->assertSame(3, ResearchLink::query()->where('research_project_id', $project->id)->where('is_pinned', true)->count());
        $this->assertSame(ProjectResearchJourneyService::STATUS_IN_PROGRESS, $steps['documents']['status']);
        $this->assertSame(ProjectResearchJourneyService::STATUS_COMPLETED, $steps['survey_instrument']['status']);

        $this->actingAs($user)
            ->get(route('admin.projects.journey.show', ['researchProject' => $project]))
            ->assertOk()
            ->assertSeeText('Template PharmVR Baru')
            ->assertSeeText('Dokumen Riset')
            ->assertSeeText('Instrumen Survey')
            ->assertSeeText('Pertanyaan')
            ->assertDontSee('token_hash')
            ->assertDontSee('drive_file_id')
            ->assertDontSee('/validation/survey/')
            ->assertDontSee('/supervision/review/');

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Template PharmVR Baru')
            ->assertSeeText('Lanjutkan Alur Riset')
            ->assertDontSee('token_hash')
            ->assertDontSee('drive_file_id');

        $this->assertSame(0, ReviewLink::query()->count());
        $this->assertSame(0, SupervisionReviewLink::query()->count());
        $this->assertSame(0, SurveyValidationAssignment::query()->whereNotNull('token_hash')->count());
    }

    private function adminUser(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('admin');

        return $user;
    }
}
