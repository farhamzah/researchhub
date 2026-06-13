<?php

namespace Tests\Feature;

use App\Models\ExpertValidator;
use App\Models\ResearchLink;
use App\Models\ResearchProject;
use App\Models\SupervisionFeedback;
use App\Models\SupervisionReviewLink;
use App\Models\SupervisionSession;
use App\Models\SupervisionSessionResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSupervisionLinkUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_supervision_page_renders_context_resources_and_safe_form(): void
    {
        [$token, $reviewLink, $project, $session] = $this->supervisionFixture();

        $this->get(route('supervision.review.show', ['token' => $token]))
            ->assertOk()
            ->assertSeeText('MyRiset Supervision Review')
            ->assertSeeText('Review Bimbingan Riset')
            ->assertSeeText($project->title)
            ->assertSeeText($session->title)
            ->assertSeeText('Status: Aktif')
            ->assertSeeText('Petunjuk bimbingan')
            ->assertSeeText('Agenda')
            ->assertSeeText('Progress Report')
            ->assertSeeText('Questions for Supervisor')
            ->assertSeeText('Requested Feedback')
            ->assertSeeText('Shared Resources')
            ->assertSeeText('Visible Reference Link')
            ->assertSee('https://example.com/reference', false)
            ->assertSeeText('Kirim Masukan Bimbingan')
            ->assertSee('min-h-11', false)
            ->assertDontSeeText('Hidden Internal Note')
            ->assertDontSeeText('Private storage path')
            ->assertDontSee($token)
            ->assertDontSee($reviewLink->token_hash)
            ->assertDontSee('/supervision/review/'.$token)
            ->assertDontSee('supervisor.safe@example.test')
            ->assertDontSee('Drive folder');
    }

    public function test_public_supervision_errors_and_submitted_state_are_clear_and_safe(): void
    {
        [$token, $reviewLink] = $this->supervisionFixture();

        $this->from(route('supervision.review.show', ['token' => $token]))
            ->post(route('supervision.review.store', ['token' => $token]), [
                'decision' => '',
                'general_feedback' => '',
            ])
            ->assertSessionHasErrors(['decision', 'general_feedback']);

        $this->get(route('supervision.review.show', ['token' => $token]))
            ->assertOk()
            ->assertSeeText('Pilih keputusan atau rekomendasi bimbingan.')
            ->assertSeeText('Tuliskan masukan umum bimbingan.')
            ->assertDontSee($reviewLink->token_hash);

        $this->post(route('supervision.review.store', ['token' => $token]), [
            'decision' => SupervisionFeedback::DECISION_MINOR_REVISION,
            'general_feedback' => 'Perjelas fokus penelitian dan tindak lanjut revisi.',
            'revision_notes' => 'Rapikan rumusan masalah.',
            'recommended_next_steps' => 'Kirim revisi pekan depan.',
        ])->assertRedirect(route('supervision.review.show', ['token' => $token]));

        $this->get(route('supervision.review.show', ['token' => $token]))
            ->assertOk()
            ->assertSeeText('Terima kasih, masukan bimbingan telah dikirim.')
            ->assertSeeText('Peneliti dapat melihat masukan ini dan membuat tindak lanjut revisi di MyRiset.')
            ->assertDontSee($token)
            ->assertDontSee($reviewLink->token_hash);
    }

    public function test_public_supervision_unavailable_state_is_generic_and_safe(): void
    {
        [$token, $reviewLink] = $this->supervisionFixture();

        $reviewLink->markRevoked();

        $this->get(route('supervision.review.show', ['token' => $token]))
            ->assertForbidden()
            ->assertSeeText('This link is unavailable')
            ->assertSeeText('Link bimbingan tidak aktif.')
            ->assertDontSee($token)
            ->assertDontSee($reviewLink->token_hash)
            ->assertDontSeeText('Revoked');
    }

    /**
     * @return array{0: string, 1: SupervisionReviewLink, 2: ResearchProject, 3: SupervisionSession}
     */
    private function supervisionFixture(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = User::factory()->create(['email' => 'public-supervision-owner@example.test']);
        $owner->assignRole('admin');
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Public Supervision UX Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $session = SupervisionSession::create([
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Sesi Review Proposal',
            'meeting_type' => SupervisionSession::MEETING_PROPOSAL_REVIEW,
            'status' => SupervisionSession::STATUS_SHARED,
            'agenda' => 'Membahas kesiapan proposal dan instrumen.',
            'progress_report' => 'Draft proposal sudah disusun.',
            'questions' => 'Apakah fokus penelitian sudah tepat?',
            'requested_feedback' => 'Mohon masukan prioritas revisi.',
            'next_plan' => 'Menyelesaikan revisi proposal.',
        ]);
        $researchLink = ResearchLink::create([
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Visible Reference Link',
            'url' => 'https://example.com/reference',
            'category' => ResearchLink::CATEGORY_REFERENCE,
            'is_active' => true,
        ]);
        SupervisionSessionResource::create([
            'supervision_session_id' => $session->id,
            'created_by' => $owner->id,
            'resource_type' => SupervisionSessionResource::TYPE_RESEARCH_LINK,
            'resource_id' => $researchLink->id,
            'title' => 'Visible Reference Link',
            'description' => 'Reference visible to supervisor.',
            'is_visible_to_supervisor' => true,
        ]);
        SupervisionSessionResource::create([
            'supervision_session_id' => $session->id,
            'created_by' => $owner->id,
            'resource_type' => SupervisionSessionResource::TYPE_MANUAL_NOTE,
            'title' => 'Hidden Internal Note',
            'description' => 'Private storage path Drive folder',
            'is_visible_to_supervisor' => false,
        ]);
        $validator = ExpertValidator::create([
            'created_by' => $owner->id,
            'name' => 'Supervisor Aman',
            'email' => 'supervisor.safe@example.test',
            'is_active' => true,
        ]);
        $token = 'public-supervision-token-safe';
        $reviewLink = SupervisionReviewLink::create([
            'supervision_session_id' => $session->id,
            'expert_validator_id' => $validator->id,
            'created_by' => $owner->id,
            'recipient_role' => 'Promotor',
            'status' => SupervisionReviewLink::STATUS_LINK_GENERATED,
            'token_hash' => SupervisionReviewLink::hashToken($token),
            'expires_at' => now()->addDays(3),
        ]);

        return [$token, $reviewLink, $project, $session];
    }
}
