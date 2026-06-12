<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\AnalysisResult;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DriveConnection;
use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Models\ResearchLink;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_integrates_scoped_research_workspace_summaries(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = $this->adminUser('owner@example.test');
        $project = $this->project($owner, 'Owner Dissertation Project');
        $category = DocumentCategory::create([
            'name' => 'Proposal',
            'slug' => 'proposal',
            'is_default' => true,
        ]);

        Document::create([
            'project_id' => $project->id,
            'category_id' => $category->id,
            'owner_id' => $owner->id,
            'title' => 'Chapter I Draft',
            'status' => Document::STATUS_UNDER_REVIEW,
            'visibility' => Document::VISIBILITY_PROJECT,
        ]);

        $survey = Survey::create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Usability Survey',
            'status' => Survey::STATUS_PUBLISHED,
            'identity_mode' => Survey::IDENTITY_HIDDEN,
            'is_public' => true,
        ]);

        SurveyResponse::create([
            'survey_id' => $survey->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        ProjectTimelineTask::create([
            'research_project_id' => $project->id,
            'title' => 'Delayed Literature Review',
            'planned_end_date' => today()->subDay(),
            'status' => ProjectMilestone::STATUS_IN_PROGRESS,
            'progress_percentage' => 40,
            'weight' => 1,
        ]);

        ProjectTimelineTask::create([
            'research_project_id' => $project->id,
            'title' => 'Upcoming Supervisor Review',
            'planned_end_date' => today()->addDays(5),
            'status' => ProjectMilestone::STATUS_NOT_STARTED,
            'progress_percentage' => 0,
            'weight' => 1,
        ]);

        ResearchLink::create([
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Pinned Journal Index',
            'url' => 'https://journal.example.test/search?token=secret-dashboard-token',
            'category' => ResearchLink::CATEGORY_JOURNAL,
            'is_pinned' => true,
            'is_active' => true,
        ]);

        DriveConnection::create([
            'user_id' => $owner->id,
            'provider' => DriveConnection::PROVIDER_GOOGLE,
            'email' => 'drive-owner@example.test',
            'status' => DriveConnection::STATUS_CONNECTED,
            'last_connected_at' => now(),
        ]);

        $job = AnalysisJob::create([
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'created_by' => $owner->id,
            'status' => AnalysisJob::STATUS_COMPLETED,
        ]);

        AnalysisResult::create([
            'analysis_job_id' => $job->id,
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'title' => 'Descriptive Analysis Draft',
            'summary' => ['responses' => 1],
            'result_payload' => ['safe' => true],
        ]);

        $this->actingAs($owner)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Owner Dissertation Project')
            ->assertSeeText('Chapter I Draft')
            ->assertSeeText('Usability Survey')
            ->assertSeeText('1 responses')
            ->assertSeeText('Delayed Tasks')
            ->assertSeeText('Upcoming 14 Days')
            ->assertSeeText('Upcoming Supervisor Review')
            ->assertSeeText('Pinned Journal Index')
            ->assertSeeText('Journal')
            ->assertSeeText('journal.example.test')
            ->assertDontSeeText('secret-dashboard-token')
            ->assertSeeText('Connected')
            ->assertSeeText('Descriptive Analysis Draft')
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('data-dashboard-card="active-projects"', false)
            ->assertSee('data-dashboard-card="timeline-focus"', false)
            ->assertSee('data-dashboard-card="recent-documents"', false)
            ->assertSee('data-dashboard-card="recent-surveys"', false)
            ->assertSee('data-dashboard-card="pinned-research-links"', false);
    }

    public function test_dashboard_does_not_leak_other_users_workspace_data(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = $this->adminUser('owner@example.test');
        $other = $this->adminUser('other@example.test');
        $otherProject = $this->project($other, 'Other User Private Project');
        $category = DocumentCategory::create([
            'name' => 'Private Category',
            'slug' => 'private-category',
        ]);

        Document::create([
            'project_id' => $otherProject->id,
            'category_id' => $category->id,
            'owner_id' => $other->id,
            'title' => 'Other User Document',
        ]);

        $survey = Survey::create([
            'project_id' => $otherProject->id,
            'created_by' => $other->id,
            'title' => 'Other User Survey',
        ]);

        ProjectTimelineTask::create([
            'research_project_id' => $otherProject->id,
            'title' => 'Other User Timeline Task',
            'planned_end_date' => today()->addDays(2),
        ]);

        ResearchLink::create([
            'research_project_id' => $otherProject->id,
            'created_by' => $other->id,
            'title' => 'Other User Pinned Link',
            'url' => 'https://private.example.test/resource',
            'category' => ResearchLink::CATEGORY_DATASET,
            'is_pinned' => true,
            'is_active' => true,
        ]);

        $job = AnalysisJob::create([
            'project_id' => $otherProject->id,
            'survey_id' => $survey->id,
            'created_by' => $other->id,
            'status' => AnalysisJob::STATUS_COMPLETED,
        ]);

        AnalysisResult::create([
            'analysis_job_id' => $job->id,
            'project_id' => $otherProject->id,
            'survey_id' => $survey->id,
            'title' => 'Other User Analysis',
            'result_payload' => ['private' => true],
        ]);

        $this->actingAs($owner)
            ->get('/admin')
            ->assertOk()
            ->assertDontSeeText('Other User Private Project')
            ->assertDontSeeText('Other User Document')
            ->assertDontSeeText('Other User Survey')
            ->assertDontSeeText('Other User Timeline Task')
            ->assertDontSeeText('Other User Pinned Link')
            ->assertDontSeeText('Other User Analysis')
            ->assertDontSeeText('private.example.test')
            ->assertSeeText('No projects yet. Create your first research project')
            ->assertSeeText('No documents yet. Upload or create your first research document.')
            ->assertSeeText('No surveys yet. Create your first survey instrument.')
            ->assertSeeText('No pinned research links yet.');
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
            'target_finished_at' => today()->addMonths(3),
        ]);
    }
}
