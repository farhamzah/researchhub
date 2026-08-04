<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\AnalysisResult;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DriveConnection;
use App\Models\ExpertValidator;
use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Models\ResearchLink;
use App\Models\ResearchProject;
use App\Models\SupervisionFeedback;
use App\Models\SupervisionFollowUpItem;
use App\Models\SupervisionReviewLink;
use App\Models\SupervisionSession;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
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

        $validator = ExpertValidator::create([
            'created_by' => $owner->id,
            'name' => 'Dashboard Validator',
            'email' => 'validator-dashboard@example.test',
            'phone' => '08123456789',
            'institution' => 'Private Lab',
            'is_active' => true,
        ]);
        $round = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Instrument Validation Round',
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);
        SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->id,
            'expert_validator_id' => $validator->id,
            'status' => SurveyValidationAssignment::STATUS_OPENED,
            'token_hash' => SurveyValidationAssignment::hashToken('raw-validation-dashboard-token'),
            'opened_at' => now()->subDay(),
            'created_by' => $owner->id,
        ]);

        $session = SupervisionSession::create([
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Bab 2 Supervision Feedback',
            'meeting_type' => SupervisionSession::MEETING_CHAPTER_REVIEW,
            'status' => SupervisionSession::STATUS_REVISION_NEEDED,
            'submitted_at' => now()->subDay(),
        ]);
        $reviewLink = SupervisionReviewLink::create([
            'supervision_session_id' => $session->id,
            'expert_validator_id' => $validator->id,
            'created_by' => $owner->id,
            'status' => SupervisionReviewLink::STATUS_SUBMITTED,
            'token_hash' => SupervisionReviewLink::hashToken('raw-supervision-dashboard-token'),
            'submitted_at' => now()->subDay(),
        ]);
        SupervisionFeedback::create([
            'supervision_review_link_id' => $reviewLink->id,
            'supervision_session_id' => $session->id,
            'decision' => SupervisionFeedback::DECISION_MAJOR_REVISION,
            'general_feedback' => 'Private long feedback should stay out of dashboard cards.',
        ]);
        SupervisionFollowUpItem::create([
            'supervision_session_id' => $session->id,
            'created_by' => $owner->id,
            'assigned_to' => $owner->id,
            'title' => 'Revise conceptual framework',
            'status' => SupervisionFollowUpItem::STATUS_IN_PROGRESS,
            'priority' => SupervisionFollowUpItem::PRIORITY_HIGH,
            'due_date' => today()->subDay(),
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
            ->assertSeeText('1 respons')
            ->assertSeeText('Tugas Terlambat')
            ->assertSeeText('Jatuh Tempo 14 Hari')
            ->assertSeeText('Upcoming Supervisor Review')
            ->assertSeeText('Pusat Tindakan')
            ->assertSeeText('Yang Perlu Dikerjakan Sekarang')
            ->assertSeeText('Tindak Lanjut Revisi')
            ->assertSeeText('Revise conceptual framework')
            ->assertSeeText('Validasi Ahli Menunggu')
            ->assertSeeText('Instrument Validation Round')
            ->assertSeeText('0 / 1 terkirim')
            ->assertSeeText('Feedback Bimbingan')
            ->assertSeeText('Bab 2 Supervision Feedback')
            ->assertSeeText('Revisi Mayor')
            ->assertSeeText('Risiko Timeline')
            ->assertSeeText('Delayed Literature Review')
            ->assertSee('data-dashboard-card="action-center"', false)
            ->assertSee('data-dashboard-card="pending-follow-ups"', false)
            ->assertSee('data-dashboard-card="validation-pending"', false)
            ->assertSee('data-dashboard-card="supervision-feedback"', false)
            ->assertSee('data-dashboard-card="timeline-risks"', false)
            ->assertSeeText('Pinned Journal Index')
            ->assertSeeText('Journal')
            ->assertSeeText('journal.example.test')
            ->assertDontSeeText('secret-dashboard-token')
            ->assertDontSee('raw-validation-dashboard-token')
            ->assertDontSee(SurveyValidationAssignment::hashToken('raw-validation-dashboard-token'))
            ->assertDontSee('raw-supervision-dashboard-token')
            ->assertDontSee(SupervisionReviewLink::hashToken('raw-supervision-dashboard-token'))
            ->assertDontSeeText('validator-dashboard@example.test')
            ->assertDontSeeText('08123456789')
            ->assertDontSeeText('Private long feedback should stay out of dashboard cards.')
            ->assertSeeText('Terhubung')
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

        $otherValidator = ExpertValidator::create([
            'created_by' => $other->id,
            'name' => 'Other User Validator',
            'email' => 'other-validator@example.test',
            'is_active' => true,
        ]);
        $otherRound = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $otherProject->id,
            'created_by' => $other->id,
            'title' => 'Other User Validation Round',
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);
        SurveyValidationAssignment::create([
            'survey_validation_round_id' => $otherRound->id,
            'expert_validator_id' => $otherValidator->id,
            'status' => SurveyValidationAssignment::STATUS_OPENED,
            'token_hash' => SurveyValidationAssignment::hashToken('other-raw-validation-token'),
            'created_by' => $other->id,
        ]);
        $otherSession = SupervisionSession::create([
            'research_project_id' => $otherProject->id,
            'created_by' => $other->id,
            'title' => 'Other User Supervision',
            'status' => SupervisionSession::STATUS_REVISION_NEEDED,
            'submitted_at' => now(),
        ]);
        $otherReviewLink = SupervisionReviewLink::create([
            'supervision_session_id' => $otherSession->id,
            'created_by' => $other->id,
            'recipient_name' => 'Other Supervisor',
            'status' => SupervisionReviewLink::STATUS_SUBMITTED,
            'token_hash' => SupervisionReviewLink::hashToken('other-supervision-token'),
            'submitted_at' => now(),
        ]);
        SupervisionFeedback::create([
            'supervision_review_link_id' => $otherReviewLink->id,
            'supervision_session_id' => $otherSession->id,
            'decision' => SupervisionFeedback::DECISION_MAJOR_REVISION,
            'general_feedback' => 'Other private supervision feedback',
        ]);
        SupervisionFollowUpItem::create([
            'supervision_session_id' => $otherSession->id,
            'created_by' => $other->id,
            'title' => 'Other User Follow Up',
            'status' => SupervisionFollowUpItem::STATUS_TODO,
            'priority' => SupervisionFollowUpItem::PRIORITY_URGENT,
            'due_date' => today()->subDay(),
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
            ->assertDontSeeText('Other User Validation Round')
            ->assertDontSeeText('Other User Supervision')
            ->assertDontSeeText('Other User Follow Up')
            ->assertDontSee('other-raw-validation-token')
            ->assertDontSee(SurveyValidationAssignment::hashToken('other-raw-validation-token'))
            ->assertDontSee('other-supervision-token')
            ->assertDontSee(SupervisionReviewLink::hashToken('other-supervision-token'))
            ->assertDontSeeText('private.example.test')
            ->assertSeeText('Belum ada project. Buat project riset pertama')
            ->assertSeeText('Belum ada dokumen. Tambahkan proposal')
            ->assertSeeText('Belum ada survey. Buat instrumen pertama')
            ->assertSeeText('Belum ada link riset tersemat.')
            ->assertSeeText('Belum ada tindak lanjut, validasi, feedback bimbingan, atau risiko timeline yang perlu ditangani.')
            ->assertSeeText('Tidak ada follow-up revisi yang sedang berjalan.')
            ->assertSeeText('Tidak ada validasi ahli yang sedang menunggu submit.')
            ->assertSeeText('Belum ada feedback bimbingan yang perlu ditindaklanjuti.')
            ->assertSeeText('Tidak ada timeline task yang terlambat.');
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
