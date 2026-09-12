<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\SurveySupervisorReviewComment;
use App\Models\SurveySupervisorReviewer;
use App\Models\SurveySupervisorReviewRound;
use App\Models\SurveyWorkflowTransition;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyInstrumentRevisionAction;
use App\Modules\Surveys\Actions\InstallPharmVrInstrumentsV2Action;
use App\Modules\Surveys\Services\SurveyInstrumentWorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmVrSupervisorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_supervisor_reviews_locked_version_and_revision_creates_clean_draft(): void
    {
        [$owner, $survey] = $this->installedStudent();
        [$round, $reviewer, $token] = $this->openReview($owner, $survey);

        $this->get(route('supervisor-review.survey.show', ['token' => $token]))
            ->assertOk()->assertSeeText('S01-C01')->assertSeeText('Belum mempelajari')
            ->assertSeeText('Pertahankan')->assertSeeText('Diskusikan')->assertSeeText('Kirim Review Final');

        $snapshotQuestions = collect($round->fresh()->snapshot_json['pages'])->flatMap(fn (array $page): array => $page['questions']);
        $comments = $snapshotQuestions->mapWithKeys(fn (array $question): array => ['item_'.$question['id'] => [
            'comment_type' => SurveySupervisorReviewComment::TYPE_ITEM,
            'survey_question_id' => $question['id'],
            'target_key' => $question['question_key'],
            'target_label' => $question['label'],
            'decision' => $question['question_key'] === 'S01-C01' ? SurveySupervisorReviewComment::DECISION_REVISE : SurveySupervisorReviewComment::DECISION_KEEP,
            'comment' => $question['question_key'] === 'S01-C01' ? 'Perjelas konteks.' : null,
            'suggested_revision' => $question['question_key'] === 'S01-C01' ? 'Redaksi revisi pembimbing.' : null,
        ]])->all();

        $this->post(route('supervisor-review.survey.store', ['token' => $token]), [
            'comments' => $comments,
            'final_decision' => SurveySupervisorReviewer::DECISION_REVISION_REQUIRED,
            'final_notes' => 'Satu butir perlu direvisi.',
        ])->assertRedirect(route('supervisor-review.survey.show', ['token' => $token]));

        $this->assertSame(34, $reviewer->comments()->count());
        $this->assertSame(SurveyInstrumentWorkflowService::SUPERVISOR_REVISION_REQUIRED, $survey->fresh()->workflow_state);
        $this->assertNotNull($round->fresh()->finalized_at);
        $this->assertSame(4, SurveyWorkflowTransition::where('survey_id', $survey->id)->count());

        $sourceResponse = SurveyResponse::create([
            'survey_id' => $survey->id, 'response_token_hash' => hash('sha256', 'source-evidence'),
            'status' => SurveyResponse::STATUS_SUBMITTED, 'submitted_at' => now(), 'is_test_response' => true, 'excluded_from_analysis' => true,
        ]);
        $revision = app(CreateSurveyInstrumentRevisionAction::class)->handle($survey->fresh(), $owner);

        $this->assertSame('2.1', $revision->instrument_version);
        $this->assertSame($survey->id, $revision->supersedes_survey_id);
        $this->assertSame(Survey::STATUS_DRAFT, $revision->status);
        $this->assertSame(SurveyInstrumentWorkflowService::DRAFT, $revision->workflow_state);
        $this->assertFalse($revision->is_public);
        $this->assertSame(34, $revision->questions()->count());
        $this->assertSame(0, $revision->responses()->count());
        $this->assertDatabaseHas('survey_responses', ['id' => $sourceResponse->id, 'survey_id' => $survey->id]);
        $this->assertSame($survey->questions()->orderBy('sort_order')->pluck('label')->all(), $revision->questions()->orderBy('sort_order')->pluck('label')->all());
    }

    public function test_final_evidence_is_immutable_and_report_is_data_driven_with_real_docx(): void
    {
        [$owner, $survey] = $this->installedStudent();
        [$round, $reviewer, $token] = $this->openReview($owner, $survey);
        $questions = collect($round->fresh()->snapshot_json['pages'])->flatMap(fn (array $page): array => $page['questions']);
        $comments = $questions->mapWithKeys(fn (array $question): array => ['item_'.$question['id'] => [
            'comment_type' => 'item', 'survey_question_id' => $question['id'], 'target_key' => $question['question_key'],
            'target_label' => $question['label'], 'decision' => SurveySupervisorReviewComment::DECISION_KEEP,
        ]])->all();
        $this->post(route('supervisor-review.survey.store', ['token' => $token]), [
            'comments' => $comments, 'final_decision' => SurveySupervisorReviewer::DECISION_SUPERVISOR_APPROVED, 'final_notes' => 'Seluruh butir dipertahankan.',
        ])->assertRedirect();

        $round->refresh();
        $this->actingAs($owner)->get(route('admin.surveys.supervisor-review.report', compact('survey', 'round')))
            ->assertOk()->assertSeeText('34 Pertahankan')->assertSeeText('0 Revisi')->assertSeeText('Seluruh butir dipertahankan.')
            ->assertSeeText('Redaksi saat direview')->assertSeeText('Redaksi revisi');
        $docx = $this->actingAs($owner)->get(route('admin.surveys.supervisor-review.report.docx', compact('survey', 'round')));
        $docx->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertStringStartsWith('PK', $docx->getContent());

        $comment = $reviewer->comments()->firstOrFail();
        $this->expectException(\LogicException::class);
        $comment->update(['comment' => 'Tidak boleh berubah']);
    }

    public function test_unassigned_or_tampered_review_access_is_rejected(): void
    {
        [$owner, $survey] = $this->installedStudent();
        [$round, , $token] = $this->openReview($owner, $survey);
        $other = app(InstallPharmVrInstrumentsV2Action::class)->handle($owner, $survey->project, 'researcher@example.test')[1]['survey'];
        $foreignQuestion = $other->questions()->firstOrFail();

        $this->get(route('supervisor-review.survey.show', ['token' => 'not-assigned']))->assertNotFound();
        $this->post(route('supervisor-review.survey.store', ['token' => $token]), [
            'comments' => [[
                'comment_type' => 'item', 'survey_question_id' => $foreignQuestion->id, 'target_key' => $foreignQuestion->question_key,
                'target_label' => $foreignQuestion->label, 'decision' => SurveySupervisorReviewComment::DECISION_KEEP,
            ]],
            'final_decision' => SurveySupervisorReviewer::DECISION_SUPERVISOR_APPROVED,
        ])->assertSessionHasErrors('comments');
        $this->assertNull($round->reviewers()->firstOrFail()->submitted_at);
    }

    /** @return array{0: User, 1: Survey} */
    private function installedStudent(): array
    {
        $this->seed(RolePermissionSeeder::class);
        $owner = User::factory()->create(['email' => 'researcher@example.test']);
        $owner->assignRole('admin');
        $project = ResearchProject::create(['owner_id' => $owner->id, 'title' => 'Riset PharmVR', 'slug' => 'workflow-pharmvr', 'status' => ResearchProject::STATUS_ACTIVE]);
        $survey = app(InstallPharmVrInstrumentsV2Action::class)->handle($owner, $project, 'researcher@example.test')[0]['survey'];

        return [$owner, $survey];
    }

    /** @return array{0: SurveySupervisorReviewRound, 1: SurveySupervisorReviewer, 2: string} */
    private function openReview(User $owner, Survey $survey): array
    {
        $this->actingAs($owner)->post(route('admin.surveys.supervisor-review.rounds.store', ['survey' => $survey]), [
            'title' => 'Review Pembimbing v2.0', 'purpose' => 'Review ilmiah', 'status' => SurveySupervisorReviewRound::STATUS_OPEN,
        ])->assertRedirect();
        $round = $survey->supervisorReviewRounds()->firstOrFail();
        $this->actingAs($owner)->post(route('admin.surveys.supervisor-review.reviewers.store', compact('survey', 'round')), [
            'supervisor_name' => 'Prof. Pembimbing', 'supervisor_email' => 'supervisor@example.test', 'supervisor_code' => 'SPV-01', 'role' => 'Promotor',
        ])->assertRedirect();
        $reviewer = $round->reviewers()->firstOrFail();
        $this->actingAs($owner)->post(route('admin.surveys.supervisor-review.reviewers.generate-link', compact('survey', 'reviewer')))->assertSessionHas('generated_supervisor_review_url');
        $url = session('generated_supervisor_review_url');

        return [$round->refresh(), $reviewer->refresh(), basename(parse_url($url, PHP_URL_PATH))];
    }
}
