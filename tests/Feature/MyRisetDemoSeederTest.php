<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\ExpertValidator;
use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Models\ResearchLink;
use App\Models\ResearchProject;
use App\Models\SupervisionFollowUpItem;
use App\Models\SupervisionReviewLink;
use App\Models\SupervisionSession;
use App\Models\SupervisionSessionResource;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\SurveyValidationScore;
use App\Models\User;
use Database\Seeders\MyRisetDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyRisetDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_myriset_demo_seeder_runs_and_is_idempotent(): void
    {
        $this->seed(MyRisetDemoSeeder::class);

        $counts = $this->demoCounts();

        $this->seed(MyRisetDemoSeeder::class);

        $this->assertSame($counts, $this->demoCounts());
    }

    public function test_myriset_demo_pack_creates_complete_safe_demo_data(): void
    {
        $this->seed(MyRisetDemoSeeder::class);

        $admin = User::query()->where('email', 'admin@researchhub.test')->firstOrFail();
        $this->assertTrue($admin->hasRole('super_admin'));

        $project = ResearchProject::query()->where('slug', 'disertasi-pharmvr')->firstOrFail();
        $this->assertSame('Disertasi PharmVR', $project->title);

        $survey = Survey::query()->where('slug', 'angket-evaluasi-pembelajaran-pharmvr')->firstOrFail();
        $this->assertSame(5, $survey->questions()->count());
        $this->assertSame(4, $survey->questions()->where('type', SurveyQuestion::TYPE_LIKERT)->count());
        $this->assertSame(1, $survey->questions()->where('type', SurveyQuestion::TYPE_SHORT_TEXT)->count());

        $round = SurveyValidationRound::query()
            ->where('title', 'Validasi Instrumen Angket Evaluasi PharmVR')
            ->firstOrFail();
        $this->assertSame(3, $round->assignments()->count());
        $this->assertSame(2, $round->assignments()->where('status', SurveyValidationAssignment::STATUS_SUBMITTED)->count());
        $this->assertSame(10, SurveyValidationScore::query()->whereHas('assignment', fn ($query) => $query->where('survey_validation_round_id', $round->id))->count());

        $session = SupervisionSession::query()
            ->where('title', 'Bimbingan Proposal dan Validasi Instrumen PharmVR')
            ->firstOrFail();
        $this->assertSame(6, $session->resources()->count());
        $this->assertSame(3, $session->followUpItems()->count());

        $this->assertSame(5, Document::query()->where('project_id', $project->id)->count());
        $this->assertSame(5, ResearchLink::query()->where('research_project_id', $project->id)->where('is_pinned', true)->count());
        $this->assertSame(5, ProjectMilestone::query()->where('research_project_id', $project->id)->count());
        $this->assertSame(5, ProjectTimelineTask::query()->where('research_project_id', $project->id)->count());

        $this->assertSame(3, ExpertValidator::query()->where('email', 'like', '%@example.test')->count());
        $this->assertSame(0, SurveyValidationAssignment::query()->whereNotNull('token_hash')->count());
        $this->assertSame(0, SupervisionReviewLink::query()->whereNotNull('token_hash')->count());
    }

    public function test_myriset_demo_dashboard_renders_action_center_without_sensitive_data(): void
    {
        $this->seed(MyRisetDemoSeeder::class);

        $admin = User::query()->where('email', 'admin@researchhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Action Center')
            ->assertSeeText('Disertasi PharmVR')
            ->assertSeeText('Revisi butir angket tentang keterlibatan belajar')
            ->assertSeeText('Validasi Instrumen Angket Evaluasi PharmVR')
            ->assertSeeText('Bimbingan Proposal dan Validasi Instrumen PharmVR')
            ->assertSeeText('Revisi instrumen validasi ahli')
            ->assertDontSee('raw-validation-dashboard-token')
            ->assertDontSee('raw-supervision-dashboard-token')
            ->assertDontSee(SurveyValidationAssignment::hashToken('raw-validation-dashboard-token'))
            ->assertDontSee(SupervisionReviewLink::hashToken('raw-supervision-dashboard-token'))
            ->assertDontSee('validator.materi@example.test')
            ->assertDontSee('Struktur instrumen sudah baik, tetapi beberapa butir perlu diperjelas');
    }

    public function test_myriset_e2e_qa_checklist_exists(): void
    {
        $path = base_path('docs/MYRISET_E2E_QA_CHECKLIST.md');

        $this->assertFileExists($path);
        $this->assertStringContainsString('Dashboard Action Center', file_get_contents($path));
        $this->assertStringContainsString('Google Drive Settings', file_get_contents($path));
    }

    /**
     * @return array<string, int>
     */
    private function demoCounts(): array
    {
        $project = ResearchProject::query()->where('slug', 'disertasi-pharmvr')->firstOrFail();
        $survey = Survey::query()->where('slug', 'angket-evaluasi-pembelajaran-pharmvr')->firstOrFail();
        $round = SurveyValidationRound::query()->where('title', 'Validasi Instrumen Angket Evaluasi PharmVR')->firstOrFail();
        $session = SupervisionSession::query()->where('title', 'Bimbingan Proposal dan Validasi Instrumen PharmVR')->firstOrFail();

        return [
            'projects' => ResearchProject::query()->where('slug', 'disertasi-pharmvr')->count(),
            'documents' => Document::query()->where('project_id', $project->id)->count(),
            'links' => ResearchLink::query()->where('research_project_id', $project->id)->count(),
            'milestones' => ProjectMilestone::query()->where('research_project_id', $project->id)->count(),
            'tasks' => ProjectTimelineTask::query()->where('research_project_id', $project->id)->count(),
            'questions' => SurveyQuestion::query()->where('survey_id', $survey->id)->count(),
            'assignments' => SurveyValidationAssignment::query()->where('survey_validation_round_id', $round->id)->count(),
            'scores' => SurveyValidationScore::query()->whereHas('assignment', fn ($query) => $query->where('survey_validation_round_id', $round->id))->count(),
            'supervision_resources' => SupervisionSessionResource::query()->where('supervision_session_id', $session->id)->count(),
            'follow_ups' => SupervisionFollowUpItem::query()->where('supervision_session_id', $session->id)->count(),
        ];
    }
}
