<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\ExpertValidator;
use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Models\ResearchProject;
use App\Models\SupervisionFollowUpItem;
use App\Models\SupervisionReviewLink;
use App\Models\SupervisionSession;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionScoring;
use App\Models\SurveyResponse;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\User;
use App\Modules\Projects\Services\ProjectResearchJourneyService;
use Database\Seeders\MyRisetDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectResearchJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_journey_page_renders_for_authorized_user_and_hides_sensitive_data(): void
    {
        $this->seed(RolePermissionSeeder::class);

        [$owner, $project, $survey, $round] = $this->projectWithJourneyData();

        $assignment = SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->id,
            'expert_validator_id' => ExpertValidator::create([
                'created_by' => $owner->id,
                'name' => 'Sensitive Validator',
                'email' => 'sensitive-validator@example.test',
                'is_active' => true,
            ])->id,
            'status' => SurveyValidationAssignment::STATUS_OPENED,
            'token_hash' => SurveyValidationAssignment::hashToken('raw-validation-journey-token'),
            'created_by' => $owner->id,
        ]);

        $session = SupervisionSession::create([
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Journey Supervision',
            'status' => SupervisionSession::STATUS_REVISION_NEEDED,
        ]);

        SupervisionReviewLink::create([
            'supervision_session_id' => $session->id,
            'created_by' => $owner->id,
            'recipient_name' => 'Private Supervisor',
            'status' => SupervisionReviewLink::STATUS_SUBMITTED,
            'token_hash' => SupervisionReviewLink::hashToken('raw-supervision-journey-token'),
        ]);

        $this->actingAs($owner)
            ->get(route('admin.projects.journey.show', ['researchProject' => $project]))
            ->assertOk()
            ->assertSeeText('Alur Riset')
            ->assertSeeText('Langkah berikutnya')
            ->assertSeeText('Setup Project')
            ->assertSeeText('Dokumen Riset')
            ->assertSeeText('Timeline Riset')
            ->assertSeeText('Instrumen Survey')
            ->assertSeeText('Skoring & Indikator')
            ->assertSeeText('Validasi Ahli')
            ->assertSeeText('Hasil Validasi')
            ->assertSeeText('Respons & Analisis')
            ->assertSeeText('Bimbingan')
            ->assertSeeText('Tindak Lanjut Revisi')
            ->assertSeeText('Laporan / Publikasi')
            ->assertSeeText('Perlu perhatian')
            ->assertSeeText('Periksa Timeline')
            ->assertSee(route('admin.projects.timeline.index', ['researchProject' => $project]), false)
            ->assertSee(route('admin.surveys.builder.index', ['survey' => $survey]), false)
            ->assertSee(route('admin.surveys.validation.index', ['survey' => $survey]), false)
            ->assertDontSee('raw-validation-journey-token')
            ->assertDontSee(SurveyValidationAssignment::hashToken('raw-validation-journey-token'))
            ->assertDontSee('raw-supervision-journey-token')
            ->assertDontSee(SupervisionReviewLink::hashToken('raw-supervision-journey-token'))
            ->assertDontSeeText('sensitive-validator@example.test')
            ->assertDontSeeText($assignment->token_hash);
    }

    public function test_unauthorized_user_cannot_view_another_project_journey(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = $this->adminUser('owner-journey@example.test');
        $outsider = $this->adminUser('outsider-journey@example.test');
        $project = $this->project($owner, 'Private Journey Project');

        $this->actingAs($outsider)
            ->get(route('admin.projects.journey.show', ['researchProject' => $project]))
            ->assertForbidden();
    }

    public function test_project_table_exposes_research_journey_row_action(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = $this->adminUser('project-action@example.test');
        $project = $this->project($owner, 'Project With Journey Action');

        $this->actingAs($owner)
            ->get(route('filament.admin.resources.projects.research-projects.index'))
            ->assertOk()
            ->assertSeeText('Project With Journey Action')
            ->assertSeeText('Alur Riset')
            ->assertSee(route('admin.projects.journey.show', ['researchProject' => $project]), false);
    }

    public function test_journey_service_computes_attention_states_from_existing_data(): void
    {
        $this->seed(RolePermissionSeeder::class);

        [$owner, $project, $survey, $round] = $this->projectWithJourneyData();

        SupervisionSession::create([
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Follow Up Session',
            'status' => SupervisionSession::STATUS_REVISION_NEEDED,
        ])->followUpItems()->create([
            'created_by' => $owner->id,
            'title' => 'Fix instrument wording',
            'status' => SupervisionFollowUpItem::STATUS_TODO,
        ]);

        $journey = app(ProjectResearchJourneyService::class)->build($project->fresh());
        $steps = $journey['steps']->keyBy('key');

        $this->assertSame(ProjectResearchJourneyService::STATUS_COMPLETED, $steps['project_setup']['status']);
        $this->assertSame(ProjectResearchJourneyService::STATUS_NEEDS_ATTENTION, $steps['timeline']['status']);
        $this->assertSame(ProjectResearchJourneyService::STATUS_COMPLETED, $steps['survey_instrument']['status']);
        $this->assertSame(ProjectResearchJourneyService::STATUS_NEEDS_ATTENTION, $steps['scoring_indicators']['status']);
        $this->assertSame(ProjectResearchJourneyService::STATUS_IN_PROGRESS, $steps['expert_validation']['status']);
        $this->assertSame(ProjectResearchJourneyService::STATUS_NEEDS_ATTENTION, $steps['validation_results']['status']);
        $this->assertSame(ProjectResearchJourneyService::STATUS_IN_PROGRESS, $steps['responses_analysis']['status']);
        $this->assertSame(ProjectResearchJourneyService::STATUS_NEEDS_ATTENTION, $steps['follow_up_revisions']['status']);
        $this->assertSame('Timeline Riset', $journey['next_step']['label']);
        $this->assertSame(route('admin.surveys.scoring.index', ['survey' => $survey]), $steps['scoring_indicators']['action_url']);
        $this->assertSame(route('admin.surveys.validation.index', ['survey' => $survey]), $steps['expert_validation']['action_url']);
        $this->assertSame(route('admin.surveys.validation.index', ['survey' => $survey]), $steps['validation_results']['action_url']);
        $this->assertSame(0, $round->assignments()->where('status', SurveyValidationAssignment::STATUS_SUBMITTED)->count());
    }

    public function test_journey_detects_missing_documents_and_survey_without_questions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = $this->adminUser('missing-docs@example.test');
        $project = $this->project($owner, 'Missing Documents Project');

        Survey::create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Empty Survey',
        ]);

        $journey = app(ProjectResearchJourneyService::class)->build($project->fresh());
        $steps = $journey['steps']->keyBy('key');

        $this->assertSame(ProjectResearchJourneyService::STATUS_NOT_STARTED, $steps['documents']['status']);
        $this->assertSame(ProjectResearchJourneyService::STATUS_NEEDS_ATTENTION, $steps['survey_instrument']['status']);
        $this->assertStringContainsString('belum punya pertanyaan', $steps['survey_instrument']['description']);
    }

    public function test_dashboard_shows_research_journey_and_empty_user_onboarding(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = $this->adminUser('dashboard-journey@example.test');
        $project = $this->project($owner, 'Dashboard Journey Project');

        $this->actingAs($owner)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Lanjutkan Alur Riset')
            ->assertSeeText('Dashboard Journey Project')
            ->assertSeeText('Buka Alur Riset')
            ->assertSee(route('admin.projects.journey.show', ['researchProject' => $project]), false)
            ->assertSee('data-dashboard-card="research-journey"', false);

        $newUser = $this->adminUser('empty-dashboard@example.test');

        $this->actingAs($newUser)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Selamat datang di MyRiset')
            ->assertSeeText('Buat project riset pertama')
            ->assertSeeText('Tambahkan dokumen riset')
            ->assertSeeText('Bangun instrumen survey')
            ->assertSeeText('Tambahkan validator ahli')
            ->assertSeeText('Mulai log bimbingan');
    }

    public function test_demo_project_shows_meaningful_mixed_journey_statuses(): void
    {
        $this->seed(MyRisetDemoSeeder::class);

        $admin = User::query()->where('email', 'admin@researchhub.test')->firstOrFail();
        $project = ResearchProject::query()->where('slug', 'disertasi-pharmvr')->firstOrFail();

        $journey = app(ProjectResearchJourneyService::class)->build($project);
        $statuses = $journey['steps']->pluck('status');

        $this->assertTrue($statuses->contains(ProjectResearchJourneyService::STATUS_COMPLETED));
        $this->assertTrue($statuses->contains(ProjectResearchJourneyService::STATUS_IN_PROGRESS));
        $this->assertTrue($statuses->contains(ProjectResearchJourneyService::STATUS_NEEDS_ATTENTION));
        $this->assertSame('Dokumen Riset', $journey['next_step']['label']);

        $this->actingAs($admin)
            ->get(route('admin.projects.journey.show', ['researchProject' => $project]))
            ->assertOk()
            ->assertSeeText('Disertasi PharmVR')
            ->assertSeeText('Ada dokumen akademik yang membutuhkan revisi')
            ->assertSeeText('Document Progress Summary')
            ->assertSeeText('Timeline memiliki tugas terlambat')
            ->assertSeeText('Respons & Analisis')
            ->assertSeeText('Laporan / Publikasi')
            ->assertDontSee('token_hash')
            ->assertDontSee('validator.materi@example.test')
            ->assertDontSee('MR-DEMO-001');
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: Survey, 3: SurveyValidationRound}
     */
    private function projectWithJourneyData(): array
    {
        $owner = $this->adminUser('journey-owner@example.test');
        $project = $this->project($owner, 'Journey Project');
        $category = DocumentCategory::create([
            'name' => 'Proposal',
            'slug' => 'proposal-journey',
        ]);

        Document::create([
            'project_id' => $project->id,
            'category_id' => $category->id,
            'owner_id' => $owner->id,
            'title' => 'Proposal Journey Draft',
            'status' => Document::STATUS_DRAFT,
            'visibility' => Document::VISIBILITY_PROJECT,
        ]);

        ProjectTimelineTask::create([
            'research_project_id' => $project->id,
            'title' => 'Overdue Journey Task',
            'planned_end_date' => today()->subDay(),
            'status' => ProjectMilestone::STATUS_IN_PROGRESS,
            'progress_percentage' => 30,
            'weight' => 1,
        ]);

        $survey = Survey::create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Journey Survey',
        ]);
        $question = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'journey_likert',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Journey Likert Question',
            'options' => ['scale' => [1, 2, 3, 4]],
            'settings' => ['scale' => [1, 2, 3, 4]],
            'is_required' => true,
            'sort_order' => 1,
        ]);
        SurveyQuestionScoring::create([
            'survey_id' => $survey->id,
            'survey_question_id' => $question->id,
            'is_scored' => true,
            'score_min' => 1,
            'score_max' => 4,
        ]);
        SurveyResponse::create([
            'survey_id' => $survey->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $round = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Journey Validation Round',
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);

        return [$owner, $project, $survey, $round];
    }

    private function adminUser(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('admin');

        return $user;
    }

    private function project(User $owner, string $title): ResearchProject
    {
        return ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => $title,
            'status' => ResearchProject::STATUS_ACTIVE,
            'started_at' => today()->subMonth(),
            'target_finished_at' => today()->addMonths(6),
        ]);
    }
}
