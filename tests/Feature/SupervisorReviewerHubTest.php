<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveySupervisorReviewComment;
use App\Models\SurveySupervisorReviewer;
use App\Models\SurveySupervisorReviewerHub;
use App\Models\SurveySupervisorReviewRound;
use App\Models\User;
use App\Modules\SupervisorReviews\Actions\CreateSupervisorReviewerHubAction;
use App\Modules\SupervisorReviews\Actions\GenerateSupervisorReviewLinkAction;
use App\Modules\SupervisorReviews\Services\SupervisorReviewSnapshotService;
use App\Modules\Surveys\Actions\InstallPharmVrInstrumentsV2Action;
use App\Modules\Surveys\Services\SurveyInstrumentWorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SupervisorReviewerHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_hub_link_scopes_three_instruments_and_preserves_per_instrument_final_submit(): void
    {
        [$owner, $surveys, $reviewers, $oldTokens, $snapshotHashes] = $this->reviewSet();
        $assignmentIds = $reviewers->pluck('id')->sort()->values()->all();

        $result = app(CreateSupervisorReviewerHubAction::class)->handle(
            $owner,
            $surveys->first()->project,
            $reviewers,
            now()->addDays(30),
        );
        $token = $result->rawToken;

        foreach ($oldTokens as $oldToken) {
            $this->get(route('supervisor-review.survey.show', ['token' => $oldToken]))->assertNotFound();
        }

        $this->assertSame($assignmentIds, $result->hub->reviewers->pluck('id')->sort()->values()->all());
        $this->assertSame($snapshotHashes, SurveySupervisorReviewRound::whereIn('id', array_keys($snapshotHashes))->pluck('snapshot_hash', 'id')->all());

        $this->get(route('supervisor-review.hub.show', compact('token')))
            ->assertOk()
            ->assertSeeText('Reviewer Hub PharmVR')
            ->assertSeeText('S01')
            ->assertSeeText('S02')
            ->assertSeeText('S03')
            ->assertSeeText('Belum dimulai')
            ->assertSeeText('Mulai Review');

        $studentReviewer = $reviewers->first(fn (SurveySupervisorReviewer $reviewer): bool => $reviewer->round->survey->instrument_code === 'S01-STUDENT-NEEDS');
        $this->get(route('supervisor-review.hub.instrument.show', ['token' => $token, 'reviewer' => $studentReviewer]))
            ->assertOk()
            ->assertSeeText('Info item')
            ->assertDontSeeText('Detail teknis item')
            ->assertDontSee('snapshot_hash')
            ->assertDontSee('max_selections');
        $this->assertSame(SurveySupervisorReviewer::STATUS_OPENED, $studentReviewer->fresh()->status);

        $this->post(route('supervisor-review.hub.instrument.store', ['token' => $token, 'reviewer' => $studentReviewer]), $this->reviewPayload($studentReviewer))
            ->assertRedirect(route('supervisor-review.hub.instrument.show', ['token' => $token, 'reviewer' => $studentReviewer]));

        $this->get(route('supervisor-review.hub.show', compact('token')))
            ->assertOk()
            ->assertSeeText('Dalam proses')
            ->assertSeeText('Lihat Hasil')
            ->assertSeeText('Mulai Review');

        foreach ($reviewers->where('id', '!=', $studentReviewer->id) as $reviewer) {
            $this->post(route('supervisor-review.hub.instrument.store', ['token' => $token, 'reviewer' => $reviewer]), $this->reviewPayload($reviewer))->assertRedirect();
        }

        $this->get(route('supervisor-review.hub.show', compact('token')))
            ->assertOk()
            ->assertSeeText('Status keseluruhan: Selesai')
            ->assertSeeTextInOrder(['S01', 'Lihat Hasil', 'S02', 'Lihat Hasil', 'S03', 'Lihat Hasil']);

        $this->assertTrue($reviewers->every(fn (SurveySupervisorReviewer $reviewer): bool => $reviewer->fresh()->isSubmitted()));
        $this->assertSame([34, 35, 26], $surveys->sortBy('instrument_code')->map(fn (Survey $survey): int => $survey->questions()->count())->all());
        $this->assertSame(0, $surveys->sum(fn (Survey $survey): int => $survey->responses()->count()));
    }

    public function test_hub_rejects_foreign_assignment_and_expired_token_without_marking_instruments_opened(): void
    {
        [$owner, $surveys, $reviewers] = $this->reviewSet();
        $result = app(CreateSupervisorReviewerHubAction::class)->handle($owner, $surveys->first()->project, $reviewers, now()->addDays(30));
        $token = $result->rawToken;

        $foreign = SurveySupervisorReviewer::create([
            'survey_supervisor_review_round_id' => $reviewers->first()->round->id,
            'supervisor_name' => 'Reviewer Lain',
            'supervisor_code' => 'OTHER',
            'status' => SurveySupervisorReviewer::STATUS_NOT_OPENED,
        ]);

        $this->get(route('supervisor-review.hub.instrument.show', ['token' => $token, 'reviewer' => $foreign]))->assertNotFound();
        $this->assertTrue($reviewers->every(fn (SurveySupervisorReviewer $reviewer): bool => $reviewer->fresh()->status === SurveySupervisorReviewer::STATUS_NOT_OPENED));

        $result->hub->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->get(route('supervisor-review.hub.show', compact('token')))->assertForbidden();
    }

    public function test_admin_can_manage_one_secure_hub_link_per_supervisor(): void
    {
        [$owner, $surveys, $reviewers, $oldTokens] = $this->reviewSet();
        $survey = $surveys->first();

        $this->actingAs($owner)
            ->get(route('admin.surveys.supervisor-review.hubs.index', compact('survey')))
            ->assertOk()
            ->assertSeeText('Tautan Reviewer Hub')
            ->assertSeeText('Satu pembimbing = satu tautan pribadi.')
            ->assertSeeText('Laporan Review Pembimbing')
            ->assertSeeText('Prof. Pembimbing Hub')
            ->assertSeeText('S01')
            ->assertSeeText('S02')
            ->assertSeeText('S03')
            ->assertSee('Buka hasil & laporan', false)
            ->assertSee('data-generated-hub-link="P1"', false)
            ->assertSeeText('Bersihkan tautan yang tampil di perangkat ini')
            ->assertSeeText('Buat tautan');

        $response = $this->actingAs($owner)->post(
            route('admin.surveys.supervisor-review.hubs.generate', compact('survey')),
            ['supervisor_code' => 'P1'],
        );

        $response
            ->assertRedirect(route('admin.surveys.supervisor-review.hubs.index', compact('survey')))
            ->assertSessionHas('generated_supervisor_reviewer_hub_urls.P1');

        $hub = SurveySupervisorReviewerHub::sole();
        $firstHash = $hub->token_hash;
        $this->assertTrue($hub->isAccessible());
        $this->assertSame(3, $hub->reviewers()->count());
        $this->assertTrue($reviewers->every(fn (SurveySupervisorReviewer $reviewer): bool => $reviewer->fresh()->token_hash === null));

        foreach ($oldTokens as $oldToken) {
            $this->get(route('supervisor-review.survey.show', ['token' => $oldToken]))->assertNotFound();
        }

        $this->actingAs($owner)->post(
            route('admin.surveys.supervisor-review.hubs.generate', compact('survey')),
            ['supervisor_code' => 'P1'],
        )->assertSessionHas('generated_supervisor_reviewer_hub_urls.P1');

        $this->assertSame(1, SurveySupervisorReviewerHub::count());
        $this->assertNotSame($firstHash, $hub->fresh()->token_hash);

        $this->actingAs($owner)->post(
            route('admin.surveys.supervisor-review.hubs.revoke', compact('survey', 'hub')),
        )->assertSessionHas('status', 'supervisor-reviewer-hub-link-revoked');

        $hub->refresh();
        $this->assertNull($hub->token_hash);
        $this->assertNotNull($hub->revoked_at);
        $this->assertFalse($hub->isAccessible());
    }

    public function test_unauthorized_user_cannot_open_or_manage_admin_hub_links(): void
    {
        [, $surveys] = $this->reviewSet();
        $survey = $surveys->first();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('admin.surveys.supervisor-review.hubs.index', compact('survey')))
            ->assertForbidden();

        $this->actingAs($outsider)->post(
            route('admin.surveys.supervisor-review.hubs.generate', compact('survey')),
            ['supervisor_code' => 'P1'],
        )->assertForbidden();
    }

    /** @return array{0: User, 1: Collection<int, Survey>, 2: Collection<int, SurveySupervisorReviewer>, 3: array<int, string>, 4: array<string, string>} */
    private function reviewSet(): array
    {
        $this->seed(RolePermissionSeeder::class);
        $owner = User::factory()->create(['email' => 'hub-owner@example.test']);
        $owner->assignRole('admin');
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Riset PharmVR Hub',
            'slug' => 'pharmvr-reviewer-hub',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $surveys = collect(app(InstallPharmVrInstrumentsV2Action::class)->handle($owner, $project, $owner->email))->pluck('survey');
        $workflow = app(SurveyInstrumentWorkflowService::class);
        $snapshots = app(SupervisorReviewSnapshotService::class);
        $directLinks = app(GenerateSupervisorReviewLinkAction::class);
        $reviewers = collect();
        $tokens = [];
        $snapshotHashes = [];

        foreach ($surveys as $survey) {
            $workflow->transition($survey, SurveyInstrumentWorkflowService::READY_FOR_SUPERVISOR_REVIEW, $owner);
            $workflow->transition($survey, SurveyInstrumentWorkflowService::UNDER_SUPERVISOR_REVIEW, $owner);
            $round = SurveySupervisorReviewRound::create([
                'survey_id' => $survey->id,
                'research_project_id' => $project->id,
                'created_by' => $owner->id,
                'title' => 'Review '.$survey->instrument_code,
                'instrument_version' => '2.0',
                'workflow_state_at_open' => SurveyInstrumentWorkflowService::UNDER_SUPERVISOR_REVIEW,
                'status' => SurveySupervisorReviewRound::STATUS_OPEN,
                'opened_at' => now(),
            ]);
            $snapshots->ensureSnapshot($round);
            $reviewer = $round->reviewers()->create([
                'supervisor_name' => 'Prof. Pembimbing Hub',
                'supervisor_code' => 'P1',
                'role' => 'Pembimbing',
                'status' => SurveySupervisorReviewer::STATUS_NOT_OPENED,
                'expires_at' => now()->addDays(30),
                'created_by' => $owner->id,
            ]);
            $tokens[] = $directLinks->handle($owner, $survey, $reviewer)->rawToken;
            $reviewers->push($reviewer->refresh()->load('round.survey'));
            $snapshotHashes[$round->id] = $round->fresh()->snapshot_hash;
        }

        return [$owner, $surveys, $reviewers, $tokens, $snapshotHashes];
    }

    private function reviewPayload(SurveySupervisorReviewer $reviewer): array
    {
        $questions = collect($reviewer->round->snapshot_json['pages'])->flatMap(fn (array $page): array => $page['questions']);

        return [
            'comments' => $questions->mapWithKeys(fn (array $question): array => ['item_'.$question['id'] => [
                'comment_type' => SurveySupervisorReviewComment::TYPE_ITEM,
                'survey_question_id' => $question['id'],
                'target_key' => $question['question_key'],
                'target_label' => $question['label'],
                'decision' => SurveySupervisorReviewComment::DECISION_KEEP,
            ]])->all(),
            'final_decision' => SurveySupervisorReviewer::DECISION_SUPERVISOR_APPROVED,
            'final_notes' => 'Instrumen disetujui melalui Reviewer Hub.',
        ];
    }
}
