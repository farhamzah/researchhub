<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Models\SurveySupervisorReviewer;
use App\Models\SurveySupervisorReviewRevision;
use App\Models\SurveySupervisorReviewRound;
use App\Models\SurveyValidationScore;
use App\Models\User;
use App\Modules\Analysis\Services\AnalysisPreflightQaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorInstrumentReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_round_add_supervisor_and_generate_hash_only_link(): void
    {
        [$admin, , $survey] = $this->surveyFixture();

        $this->actingAs($admin)
            ->get(route('admin.surveys.supervisor-review.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Supervisor Instrument Review')
            ->assertSeeText('Create Review Round');

        $this->actingAs($admin)
            ->post(route('admin.surveys.supervisor-review.rounds.store', ['survey' => $survey]), [
                'title' => 'Supervisor Review Round 1',
                'purpose' => 'Pre-validation review',
                'status' => SurveySupervisorReviewRound::STATUS_OPEN,
                'due_date' => now()->addWeek()->toDateString(),
            ])
            ->assertRedirect(route('admin.surveys.supervisor-review.index', ['survey' => $survey]));

        $round = SurveySupervisorReviewRound::firstOrFail();

        $this->assertNotNull($round->snapshot_json);
        $this->assertNotNull($round->snapshot_hash);

        $this->actingAs($admin)
            ->post(route('admin.surveys.supervisor-review.reviewers.store', ['survey' => $survey, 'round' => $round]), [
                'supervisor_name' => 'Prof. Pembimbing Satu',
                'supervisor_email' => 'supervisor@example.test',
                'supervisor_code' => 'SPV-1',
                'role' => 'Promotor',
            ])
            ->assertRedirect(route('admin.surveys.supervisor-review.index', ['survey' => $survey]));

        $reviewer = SurveySupervisorReviewer::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.surveys.supervisor-review.reviewers.generate-link', ['survey' => $survey, 'reviewer' => $reviewer]))
            ->assertRedirect(route('admin.surveys.supervisor-review.index', ['survey' => $survey]))
            ->assertSessionHas('generated_supervisor_review_url');

        $generatedUrl = session('generated_supervisor_review_url');

        $this->assertDatabaseHas('survey_supervisor_reviewers', [
            'id' => $reviewer->id,
            'supervisor_code' => 'SPV-1',
        ]);
        $this->assertDatabaseMissing('survey_supervisor_reviewers', ['token_hash' => $this->tokenFromUrl($generatedUrl)]);
        $this->assertNotNull($reviewer->fresh()->token_hash);
    }

    public function test_secure_supervisor_link_opens_valid_round_and_rejects_invalid_or_revoked_token(): void
    {
        [$admin, , $survey] = $this->surveyFixture();
        [$token, $reviewer] = $this->reviewerWithGeneratedLink($admin, $survey);

        $this->get(route('supervisor-review.survey.show', ['token' => $token]))
            ->assertOk()
            ->assertSeeText('Supervisor Instrument Review')
            ->assertSeeText('Intro Narasi')
            ->assertSeeText('Privacy Statement')
            ->assertSeeText('Respondent Instruction')
            ->assertSeeText('Consent Text')
            ->assertSeeText('A. Identitas')
            ->assertSeeText('Q1')
            ->assertSeeText('Kebutuhan materi CPOB');

        $this->get(route('supervisor-review.survey.show', ['token' => 'invalid-token']))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('admin.surveys.supervisor-review.reviewers.revoke-link', ['survey' => $survey, 'reviewer' => $reviewer]))
            ->assertRedirect(route('admin.surveys.supervisor-review.index', ['survey' => $survey]));

        $this->get(route('supervisor-review.survey.show', ['token' => $token]))
            ->assertForbidden()
            ->assertSeeText('Supervisor Review Link Unavailable');
    }

    public function test_supervisor_can_submit_item_section_overall_comment_and_final_decision(): void
    {
        [$admin, , $survey] = $this->surveyFixture();
        [$token, $reviewer] = $this->reviewerWithGeneratedLink($admin, $survey);
        $question = $survey->questions()->where('question_key', 'Q1')->firstOrFail();
        $page = $survey->pages()->firstOrFail();

        $this->post(route('supervisor-review.survey.store', ['token' => $token]), [
            'comments' => [
                [
                    'comment_type' => 'item',
                    'survey_question_id' => $question->id,
                    'target_key' => 'Q1',
                    'target_label' => $question->label,
                    'comment' => 'Item needs clearer wording.',
                    'suggested_revision' => 'Clarify the CPOB learning need context.',
                    'severity' => 'moderate',
                    'decision' => 'revise',
                ],
                [
                    'comment_type' => 'section',
                    'target_key' => $page->id,
                    'target_label' => $page->title,
                    'comment' => 'Section order is academically acceptable.',
                    'suggested_revision' => 'Keep section A before core questions.',
                ],
                [
                    'comment_type' => 'overall',
                    'target_key' => 'readiness',
                    'target_label' => 'Readiness for expert validation',
                    'comment' => 'Ready after minor wording revisions.',
                ],
            ],
            'final_decision' => SurveySupervisorReviewer::DECISION_MINOR_REVISIONS,
            'final_notes' => 'Proceed after wording cleanup.',
        ])
            ->assertRedirect(route('supervisor-review.survey.show', ['token' => $token]));

        $this->assertSame(SurveySupervisorReviewer::STATUS_SUBMITTED, $reviewer->fresh()->status);
        $this->assertDatabaseHas('survey_supervisor_review_comments', [
            'comment_type' => 'item',
            'target_key' => 'Q1',
            'comment' => 'Item needs clearer wording.',
        ]);
        $this->assertDatabaseHas('survey_supervisor_review_comments', [
            'comment_type' => 'section',
            'target_label' => 'A. Identitas',
        ]);
        $this->assertDatabaseHas('survey_supervisor_review_comments', [
            'comment_type' => 'overall',
            'target_key' => 'readiness',
        ]);
        $this->assertSame(3, SurveySupervisorReviewRevision::count());

        $this->get(route('supervisor-review.survey.show', ['token' => $token]))
            ->assertOk()
            ->assertSeeText('Supervisor Review Submitted');
    }

    public function test_admin_can_view_submitted_review_update_revision_matrix_and_print_report(): void
    {
        [$admin, , $survey] = $this->surveyFixture();
        [$token] = $this->reviewerWithGeneratedLink($admin, $survey);
        $question = $survey->questions()->where('question_key', 'Q1')->firstOrFail();

        $this->post(route('supervisor-review.survey.store', ['token' => $token]), [
            'comments' => [[
                'comment_type' => 'item',
                'survey_question_id' => $question->id,
                'target_key' => 'Q1',
                'target_label' => $question->label,
                'comment' => 'Needs an item-level revision.',
                'suggested_revision' => 'Use simpler language.',
                'severity' => 'minor',
                'decision' => 'revise',
            ]],
            'final_decision' => SurveySupervisorReviewer::DECISION_MINOR_REVISIONS,
            'final_notes' => 'Minor revision only.',
        ]);

        $round = SurveySupervisorReviewRound::firstOrFail();
        $revision = SurveySupervisorReviewRevision::firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.surveys.supervisor-review.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Needs an item-level revision.')
            ->assertSeeText('Revision Matrix');

        $this->actingAs($admin)
            ->put(route('admin.surveys.supervisor-review.revisions.update', ['survey' => $survey, 'revision' => $revision]), [
                'researcher_response' => 'Accepted.',
                'action_taken' => 'Question wording revised.',
                'status' => SurveySupervisorReviewRevision::STATUS_REVISED,
                'revised_version' => 'v2 / '.now()->toDateString(),
                'revised_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('admin.surveys.supervisor-review.index', ['survey' => $survey]));

        $this->assertSame('Accepted.', $revision->fresh()->researcher_response);

        $this->actingAs($admin)
            ->get(route('admin.surveys.supervisor-review.report', ['survey' => $survey, 'round' => $round]))
            ->assertOk()
            ->assertSeeText('Supervisor Instrument Review Report')
            ->assertSeeText('Question wording revised.')
            ->assertSeeText('separate from expert validation scoring');
    }

    public function test_supervisor_review_is_not_counted_as_expert_validation_and_preflight_detects_status(): void
    {
        [$admin, , $survey] = $this->surveyFixture();
        [$token] = $this->reviewerWithGeneratedLink($admin, $survey);
        $question = $survey->questions()->where('question_key', 'Q1')->firstOrFail();

        $qaBefore = app(AnalysisPreflightQaService::class)->build($survey->fresh(), $admin, AnalysisPreflightQaService::SCOPE_FULL_ANALYSIS_PACKAGE);
        $this->assertSame('failed', collect($qaBefore['checks'])->firstWhere('check_key', 'supervisor_review.submitted')['status']);

        $this->post(route('supervisor-review.survey.store', ['token' => $token]), [
            'comments' => [[
                'comment_type' => 'item',
                'survey_question_id' => $question->id,
                'target_key' => 'Q1',
                'target_label' => $question->label,
                'comment' => 'Review comment for preflight.',
                'suggested_revision' => 'Revise item.',
                'severity' => 'minor',
                'decision' => 'revise',
            ]],
            'final_decision' => SurveySupervisorReviewer::DECISION_MINOR_REVISIONS,
            'final_notes' => 'Ready with revisions.',
        ]);

        $revision = SurveySupervisorReviewRevision::firstOrFail();
        $this->actingAs($admin)->put(route('admin.surveys.supervisor-review.revisions.update', ['survey' => $survey, 'revision' => $revision]), [
            'researcher_response' => 'Accepted',
            'action_taken' => 'Updated wording',
            'status' => SurveySupervisorReviewRevision::STATUS_REVISED,
            'revised_version' => 'v2',
            'revised_at' => now()->format('Y-m-d H:i:s'),
        ]);

        SurveySupervisorReviewRound::firstOrFail()->update(['status' => SurveySupervisorReviewRound::STATUS_COMPLETED]);

        $qaAfter = app(AnalysisPreflightQaService::class)->build($survey->fresh(), $admin, AnalysisPreflightQaService::SCOPE_FULL_ANALYSIS_PACKAGE);
        $this->assertSame('passed', collect($qaAfter['checks'])->firstWhere('check_key', 'supervisor_review.submitted')['status']);
        $this->assertSame('passed', collect($qaAfter['checks'])->firstWhere('check_key', 'supervisor_review.researcher_response')['status']);
        $this->assertSame('passed', collect($qaAfter['checks'])->firstWhere('check_key', 'supervisor_review.ready_for_validation')['status']);
        $this->assertSame(0, SurveyValidationScore::count());
    }

    public function test_snapshot_shows_instrument_changed_after_round_opened(): void
    {
        [$admin, , $survey] = $this->surveyFixture();

        $this->actingAs($admin)->post(route('admin.surveys.supervisor-review.rounds.store', ['survey' => $survey]), [
            'title' => 'Snapshot Round',
            'purpose' => 'Snapshot proof',
            'status' => SurveySupervisorReviewRound::STATUS_OPEN,
        ]);

        $round = SurveySupervisorReviewRound::firstOrFail();
        $survey->questions()->where('question_key', 'Q1')->firstOrFail()->update(['label' => 'Updated question after snapshot']);

        $this->actingAs($admin)
            ->get(route('admin.surveys.supervisor-review.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Instrument changed after snapshot');
    }

    private function adminUser(string $email = 'supervisor-review-admin@example.test'): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: Survey}
     */
    private function surveyFixture(): array
    {
        $owner = $this->adminUser();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'PharmVR Supervisor Review Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = Survey::create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Student Questionnaire PharmVR',
            'description' => 'Needs analysis instrument',
            'intro_title' => 'Intro Narasi',
            'intro_text' => 'Pengantar kuesioner untuk mahasiswa.',
            'privacy_statement' => 'Data hanya digunakan untuk penelitian.',
            'respondent_instruction' => 'Isi sesuai pengalaman belajar.',
            'consent_text' => 'Saya bersedia melanjutkan.',
            'status' => Survey::STATUS_DRAFT,
            'identity_mode' => Survey::IDENTITY_HIDDEN,
            'instrument_type' => Survey::INSTRUMENT_ANALYSIS_STUDENT,
            'is_public' => false,
        ]);
        $page = SurveyPage::create([
            'survey_id' => $survey->id,
            'title' => 'A. Identitas',
            'description' => 'Bagian identitas responden.',
            'sort_order' => 1,
        ]);

        SurveyQuestion::create([
            'survey_id' => $survey->id,
            'page_id' => $page->id,
            'question_key' => 'Q1',
            'type' => SurveyQuestion::TYPE_MULTIPLE_CHOICE,
            'label' => 'Kebutuhan materi CPOB',
            'options' => ['choices' => ['Alur produksi', 'Sanitasi', 'Dokumentasi']],
            'settings' => ['max_selections' => 3],
            'is_required' => true,
            'sort_order' => 1,
        ]);

        SurveyQuestion::create([
            'survey_id' => $survey->id,
            'page_id' => $page->id,
            'question_key' => 'Q2',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'PharmVR membantu memahami CPOB',
            'options' => ['scale' => [1, 2, 3, 4, 5]],
            'is_required' => true,
            'sort_order' => 2,
        ]);

        return [$owner, $project, $survey];
    }

    /**
     * @return array{0: string, 1: SurveySupervisorReviewer}
     */
    private function reviewerWithGeneratedLink(User $admin, Survey $survey): array
    {
        $round = SurveySupervisorReviewRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $survey->project_id,
            'created_by' => $admin->id,
            'title' => 'Supervisor Review Round',
            'purpose' => 'Pre-validation supervisor review',
            'status' => SurveySupervisorReviewRound::STATUS_OPEN,
            'opened_at' => now(),
            'snapshot_json' => ['seeded' => true],
            'snapshot_hash' => hash('sha256', 'seeded'),
            'snapshot_taken_at' => now(),
        ]);

        $reviewer = SurveySupervisorReviewer::create([
            'survey_supervisor_review_round_id' => $round->id,
            'supervisor_name' => 'Prof. Pembimbing',
            'supervisor_email' => 'pembimbing@example.test',
            'supervisor_code' => 'SPV-1',
            'role' => 'Promotor',
            'status' => SurveySupervisorReviewer::STATUS_NOT_OPENED,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.surveys.supervisor-review.reviewers.generate-link', ['survey' => $survey, 'reviewer' => $reviewer]))
            ->assertRedirect(route('admin.surveys.supervisor-review.index', ['survey' => $survey]))
            ->assertSessionHas('generated_supervisor_review_url');

        return [$this->tokenFromUrl(session('generated_supervisor_review_url')), $reviewer->refresh()];
    }

    private function tokenFromUrl(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH) ?: '');
    }
}
