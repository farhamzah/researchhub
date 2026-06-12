<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ExpertValidator;
use App\Models\ExpertValidatorProject;
use App\Models\ResearchProject;
use App\Models\Respondent;
use App\Models\SupervisionFeedback;
use App\Models\SupervisionReviewLink;
use App\Models\SupervisionSession;
use App\Models\Survey;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_session_generate_secure_link_and_supervisor_can_submit_feedback(): void
    {
        [$owner, $project, $validator] = $this->projectWithSupervisor();

        $this->actingAs($owner)
            ->post(route('admin.projects.supervision.sessions.store', ['researchProject' => $project]), $this->sessionPayload())
            ->assertRedirect(route('admin.projects.supervision.index', ['researchProject' => $project]));

        $session = SupervisionSession::query()->firstOrFail();
        $this->assertSame(SupervisionSession::STATUS_DRAFT, $session->status);

        $generateResponse = $this->actingAs($owner)
            ->post(route('admin.projects.supervision.links.generate', ['researchProject' => $project, 'session' => $session]), [
                'expert_validator_id' => $validator->id,
                'recipient_role' => 'Promotor',
                'expires_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('admin.projects.supervision.index', ['researchProject' => $project]))
            ->assertSessionHas('generated_supervision_url');

        $generatedUrl = $generateResponse->getSession()->get('generated_supervision_url');
        $token = basename((string) parse_url($generatedUrl, PHP_URL_PATH));
        $link = SupervisionReviewLink::query()->firstOrFail();

        $this->assertNotSame($token, $link->token_hash);
        $this->assertSame(SupervisionReviewLink::hashToken($token), $link->token_hash);
        $this->assertSame(SupervisionSession::STATUS_SHARED, $session->fresh()->status);

        $this->actingAs($owner)
            ->get(route('admin.projects.supervision.index', ['researchProject' => $project]))
            ->assertOk()
            ->assertSee('Supervision / Bimbingan')
            ->assertSee('Copy-Ready')
            ->assertSee('Generate Secure Supervisor Link')
            ->assertSee($token)
            ->assertDontSee($link->token_hash);

        $this->actingAs($owner)
            ->get(route('admin.projects.supervision.index', ['researchProject' => $project]))
            ->assertOk()
            ->assertDontSee($link->token_hash)
            ->assertDontSee($token);

        $this->get(route('supervision.review.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('MyRiset Supervision Review')
            ->assertDontSee('ResearchHub Supervision Review')
            ->assertSee('Bab 1 Draft Review')
            ->assertSee('Progress minggu ini')
            ->assertSee('Submit Supervisor Feedback')
            ->assertDontSee('Back to Projects')
            ->assertDontSee('Respondent Rahasia')
            ->assertDontSee('secret@example.test')
            ->assertDontSee($link->token_hash);

        $this->post(route('supervision.review.store', ['token' => $token]), [
            'decision' => SupervisionFeedback::DECISION_MINOR_REVISION,
            'general_feedback' => 'Perbaiki fokus rumusan masalah dan perjelas batasan penelitian.',
            'revision_notes' => 'Tambahkan rujukan terbaru.',
            'recommended_next_steps' => 'Kirim revisi pekan depan.',
            'supervisor_note' => 'Catatan internal pembimbing.',
        ])->assertRedirect(route('supervision.review.show', ['token' => $token]));

        $link->refresh();
        $session->refresh();

        $this->assertSame(SupervisionReviewLink::STATUS_SUBMITTED, $link->status);
        $this->assertSame(SupervisionSession::STATUS_REVISION_NEEDED, $session->status);
        $this->assertDatabaseHas('supervision_feedback', [
            'supervision_review_link_id' => $link->id,
            'decision' => SupervisionFeedback::DECISION_MINOR_REVISION,
        ]);

        $this->post(route('supervision.review.store', ['token' => $token]), [
            'decision' => SupervisionFeedback::DECISION_APPROVED,
            'general_feedback' => 'Duplicate submission should not persist.',
        ])->assertOk()
            ->assertSee('Feedback submitted');

        $this->assertSame(1, SupervisionFeedback::query()->count());

        $logs = ActivityLog::query()
            ->whereIn('action', [
                'supervision_link.generated',
                'supervision_link.opened',
                'supervision_feedback.submitted',
            ])
            ->get();

        $this->assertCount(3, $logs);
        $encodedMetadata = $logs->pluck('metadata')->map(fn ($metadata): string => json_encode($metadata, JSON_THROW_ON_ERROR))->join("\n");
        $this->assertStringNotContainsString($token, $encodedMetadata);
        $this->assertStringNotContainsString($generatedUrl, $encodedMetadata);
        $this->assertStringNotContainsString('Perbaiki fokus rumusan masalah', $encodedMetadata);
    }

    public function test_unauthorized_user_cannot_manage_inaccessible_project_supervision(): void
    {
        [, $project] = $this->projectWithSupervisor('owner@example.test');
        $outsider = $this->adminUser('outsider@example.test');

        $this->actingAs($outsider)
            ->post(route('admin.projects.supervision.sessions.store', ['researchProject' => $project]), $this->sessionPayload())
            ->assertForbidden();
    }

    public function test_invalid_expired_and_revoked_supervision_tokens_are_safe(): void
    {
        [$owner, $project, $validator] = $this->projectWithSupervisor();
        $session = SupervisionSession::create([
            ...$this->sessionPayload(),
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
        ]);

        $token = $this->generateToken($owner, $project, $session, $validator);
        $link = SupervisionReviewLink::query()->firstOrFail();

        $this->get(route('supervision.review.show', ['token' => 'not-a-real-token']))
            ->assertNotFound();

        $link->forceFill([
            'expires_at' => now()->subMinute(),
            'status' => SupervisionReviewLink::STATUS_LINK_GENERATED,
        ])->save();

        $this->get(route('supervision.review.show', ['token' => $token]))
            ->assertForbidden()
            ->assertSee('This link is unavailable');

        $this->post(route('supervision.review.store', ['token' => $token]), [
            'decision' => SupervisionFeedback::DECISION_APPROVED,
            'general_feedback' => 'Should not submit expired link.',
        ])->assertForbidden();

        $link->forceFill([
            'expires_at' => now()->addDay(),
            'status' => SupervisionReviewLink::STATUS_REVOKED,
            'revoked_at' => now(),
        ])->save();

        $this->get(route('supervision.review.show', ['token' => $token]))
            ->assertForbidden()
            ->assertSee('This link is unavailable');
    }

    public function test_project_resource_exposes_supervision_action(): void
    {
        [$owner, $project] = $this->projectWithSupervisor();

        $this->actingAs($owner)
            ->get('/admin/projects/research-projects')
            ->assertOk()
            ->assertSee('Supervision');

        $this->actingAs($owner)
            ->get(route('admin.projects.supervision.index', ['researchProject' => $project]))
            ->assertOk()
            ->assertSee('No supervision sessions yet.');
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: ExpertValidator}
     */
    private function projectWithSupervisor(string $email = 'admin@example.test'): array
    {
        $owner = $this->adminUser($email);
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Supervision Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $validator = ExpertValidator::create([
            'created_by' => $owner->id,
            'name' => 'Prof Supervisor',
            'email' => 'supervisor@example.test',
            'institution' => 'Graduate School',
            'is_active' => true,
        ]);
        ExpertValidatorProject::create([
            'research_project_id' => $project->id,
            'expert_validator_id' => $validator->id,
            'role' => ExpertValidatorProject::ROLE_SUPERVISOR,
            'status' => ExpertValidatorProject::STATUS_ACTIVE,
            'created_by' => $owner->id,
        ]);

        $survey = Survey::create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Private Survey',
            'status' => Survey::STATUS_DRAFT,
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);
        Respondent::create([
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'name' => 'Respondent Rahasia',
            'email' => 'secret@example.test',
        ]);

        return [$owner, $project, $validator];
    }

    private function adminUser(string $email): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(): array
    {
        return [
            'title' => 'Bab 1 Draft Review',
            'meeting_type' => SupervisionSession::MEETING_CHAPTER_REVIEW,
            'status' => SupervisionSession::STATUS_DRAFT,
            'agenda' => 'Review arah bab pendahuluan.',
            'progress_report' => 'Progress minggu ini sudah menyusun latar belakang.',
            'questions' => 'Apakah rumusan masalah sudah cukup tajam?',
            'requested_feedback' => 'Mohon masukan struktur Bab 1.',
            'next_plan' => 'Revisi Bab 1 dan lanjut kajian pustaka.',
            'notes' => 'Catatan internal penelitian.',
            'target_date' => now()->addWeek()->format('Y-m-d'),
        ];
    }

    private function generateToken(User $owner, ResearchProject $project, SupervisionSession $session, ExpertValidator $validator): string
    {
        $response = $this->actingAs($owner)
            ->post(route('admin.projects.supervision.links.generate', ['researchProject' => $project, 'session' => $session]), [
                'expert_validator_id' => $validator->id,
                'recipient_role' => 'Promotor',
                'expires_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            ]);

        return basename((string) parse_url($response->getSession()->get('generated_supervision_url'), PHP_URL_PATH));
    }
}
