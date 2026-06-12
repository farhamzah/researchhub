<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\ExpertValidator;
use App\Models\ExpertValidatorProject;
use App\Models\ResearchLink;
use App\Models\ResearchProject;
use App\Models\Respondent;
use App\Models\SupervisionFeedback;
use App\Models\SupervisionFollowUpItem;
use App\Models\SupervisionReviewLink;
use App\Models\SupervisionSession;
use App\Models\SupervisionSessionResource;
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

    public function test_admin_can_manage_supervision_resources_and_public_link_only_shows_visible_resources(): void
    {
        [$owner, $project, $validator] = $this->projectWithSupervisor();
        $session = $this->createSession($owner, $project);
        $document = $this->createDocument($owner, $project, 'Chapter 2 Private Draft');
        $researchLink = ResearchLink::create([
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Methodology Reference',
            'url' => 'https://example.com/methodology',
            'category' => ResearchLink::CATEGORY_REFERENCE,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->post(route('admin.projects.supervision.resources.store', ['researchProject' => $project, 'session' => $session]), [
                'resource_type' => SupervisionSessionResource::TYPE_DOCUMENT,
                'resource_id' => $document->id,
                'title' => 'Draft Bab 2',
                'description' => 'Read the conceptual framework section.',
                'notes' => 'Focus on alignment with research questions.',
                'sort_order' => 1,
                'is_visible_to_supervisor' => '1',
            ])
            ->assertRedirect(route('admin.projects.supervision.index', ['researchProject' => $project]));

        $this->actingAs($owner)
            ->post(route('admin.projects.supervision.resources.store', ['researchProject' => $project, 'session' => $session]), [
                'resource_type' => SupervisionSessionResource::TYPE_RESEARCH_LINK,
                'resource_id' => $researchLink->id,
                'title' => 'Reference Link',
                'description' => 'Reference for the methodology section.',
                'sort_order' => 2,
                'is_visible_to_supervisor' => '1',
            ])
            ->assertRedirect(route('admin.projects.supervision.index', ['researchProject' => $project]));

        $this->actingAs($owner)
            ->post(route('admin.projects.supervision.resources.store', ['researchProject' => $project, 'session' => $session]), [
                'resource_type' => SupervisionSessionResource::TYPE_MANUAL_NOTE,
                'title' => 'Internal Revision Concern',
                'description' => 'This should stay private to the researcher.',
                'is_visible_to_supervisor' => '0',
            ])
            ->assertRedirect(route('admin.projects.supervision.index', ['researchProject' => $project]));

        $visibleDocumentResource = SupervisionSessionResource::query()
            ->where('resource_type', SupervisionSessionResource::TYPE_DOCUMENT)
            ->firstOrFail();

        $this->actingAs($owner)
            ->put(route('admin.projects.supervision.resources.update', ['researchProject' => $project, 'session' => $session, 'resource' => $visibleDocumentResource]), [
                'resource_type' => SupervisionSessionResource::TYPE_DOCUMENT,
                'resource_id' => $document->id,
                'title' => 'Updated Draft Bab 2',
                'description' => 'Read the revised framework section.',
                'notes' => 'Supervisor-visible note.',
                'sort_order' => 3,
                'is_visible_to_supervisor' => '1',
            ])
            ->assertRedirect(route('admin.projects.supervision.index', ['researchProject' => $project]));

        $this->actingAs($owner)
            ->get(route('admin.projects.supervision.index', ['researchProject' => $project]))
            ->assertOk()
            ->assertSee('Shared Resources')
            ->assertSee('Follow-Up Action Items')
            ->assertSee('Updated Draft Bab 2')
            ->assertSee('Resource yang dibagikan')
            ->assertSee('Chapter 2 Private Draft')
            ->assertSee('Internal Revision Concern');

        $token = $this->generateToken($owner, $project, $session, $validator);
        $link = SupervisionReviewLink::query()->firstOrFail();

        $this->get(route('supervision.review.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Shared Resources')
            ->assertSee('Updated Draft Bab 2')
            ->assertSee('Reference Link')
            ->assertSee('https://example.com/methodology')
            ->assertDontSee('Internal Revision Concern')
            ->assertDontSee('This should stay private')
            ->assertDontSee($link->token_hash)
            ->assertDontSee('Respondent Rahasia')
            ->assertDontSee('secret@example.test');

        $logs = ActivityLog::query()
            ->whereIn('action', ['supervision_resource.created', 'supervision_resource.updated'])
            ->get();

        $this->assertCount(4, $logs);
        $encodedMetadata = $logs->pluck('metadata')->map(fn ($metadata): string => json_encode($metadata, JSON_THROW_ON_ERROR))->join("\n");
        $this->assertStringNotContainsString('https://example.com/methodology', $encodedMetadata);
        $this->assertStringNotContainsString('Supervisor-visible note', $encodedMetadata);
    }

    public function test_supervision_resource_management_enforces_url_safety_and_project_scope(): void
    {
        [$owner, $project] = $this->projectWithSupervisor('resource-owner@example.test');
        $session = $this->createSession($owner, $project);
        [$otherOwner, $otherProject] = $this->projectWithSupervisor('other-owner@example.test');
        $otherDocument = $this->createDocument($otherOwner, $otherProject, 'Other Project Draft');
        $outsider = $this->adminUser('resource-outsider@example.test');

        $this->actingAs($owner)
            ->from(route('admin.projects.supervision.index', ['researchProject' => $project]))
            ->post(route('admin.projects.supervision.resources.store', ['researchProject' => $project, 'session' => $session]), [
                'resource_type' => SupervisionSessionResource::TYPE_MANUAL_URL,
                'title' => 'Unsafe URL',
                'url' => 'javascript:alert(1)',
                'is_visible_to_supervisor' => '1',
            ])
            ->assertRedirect(route('admin.projects.supervision.index', ['researchProject' => $project]))
            ->assertSessionHasErrors('url');

        $this->actingAs($owner)
            ->post(route('admin.projects.supervision.resources.store', ['researchProject' => $project, 'session' => $session]), [
                'resource_type' => SupervisionSessionResource::TYPE_DOCUMENT,
                'resource_id' => $otherDocument->id,
                'title' => 'Cross Project Document',
                'is_visible_to_supervisor' => '1',
            ])
            ->assertNotFound();

        $this->actingAs($outsider)
            ->post(route('admin.projects.supervision.resources.store', ['researchProject' => $project, 'session' => $session]), [
                'resource_type' => SupervisionSessionResource::TYPE_MANUAL_NOTE,
                'title' => 'Unauthorized Note',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('supervision_session_resources', [
            'title' => 'Cross Project Document',
        ]);
        $this->assertDatabaseMissing('supervision_session_resources', [
            'title' => 'Unauthorized Note',
        ]);
    }

    public function test_admin_can_track_and_complete_supervision_follow_up_items(): void
    {
        [$owner, $project] = $this->projectWithSupervisor();
        $session = $this->createSession($owner, $project);

        $this->actingAs($owner)
            ->post(route('admin.projects.supervision.follow-ups.store', ['researchProject' => $project, 'session' => $session]), [
                'title' => 'Revise research questions',
                'description' => 'Sharpen the question wording after supervisor feedback.',
                'source' => SupervisionFollowUpItem::SOURCE_SUPERVISOR_FEEDBACK,
                'status' => SupervisionFollowUpItem::STATUS_TODO,
                'priority' => SupervisionFollowUpItem::PRIORITY_HIGH,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
                'assigned_to' => $owner->id,
            ])
            ->assertRedirect(route('admin.projects.supervision.index', ['researchProject' => $project]));

        $item = SupervisionFollowUpItem::query()->firstOrFail();

        $this->actingAs($owner)
            ->put(route('admin.projects.supervision.follow-ups.update', ['researchProject' => $project, 'session' => $session, 'followUp' => $item]), [
                'title' => 'Revise research questions',
                'description' => 'Completed after supervisor discussion.',
                'source' => SupervisionFollowUpItem::SOURCE_SUPERVISOR_FEEDBACK,
                'status' => SupervisionFollowUpItem::STATUS_COMPLETED,
                'priority' => SupervisionFollowUpItem::PRIORITY_HIGH,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
                'assigned_to' => $owner->id,
                'completion_note' => 'Revision completed and ready for next meeting.',
            ])
            ->assertRedirect(route('admin.projects.supervision.index', ['researchProject' => $project]));

        $item->refresh();

        $this->assertSame(SupervisionFollowUpItem::STATUS_COMPLETED, $item->status);
        $this->assertNotNull($item->completed_at);

        $summary = $session->fresh()->copyReadySummary();
        $this->assertStringContainsString('Tindak lanjut:', $summary);
        $this->assertStringContainsString('Status tindak lanjut:', $summary);
        $this->assertStringContainsString('Revise research questions', $summary);
        $this->assertStringContainsString('Completed', $summary);

        $this->actingAs($owner)
            ->get(route('admin.projects.supervision.index', ['researchProject' => $project]))
            ->assertOk()
            ->assertSee('Follow-Up Action Items')
            ->assertSee('Revise research questions')
            ->assertSee('Completed');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'supervision_follow_up.completed',
        ]);
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
            'title' => 'Private Survey '.str_replace(['@', '.'], '-', $email),
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

    private function createSession(User $owner, ResearchProject $project): SupervisionSession
    {
        return SupervisionSession::create([
            ...$this->sessionPayload(),
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
        ]);
    }

    private function createDocument(User $owner, ResearchProject $project, string $title): Document
    {
        $category = DocumentCategory::firstOrCreate(
            ['slug' => 'draft'],
            ['name' => 'Draft', 'sort_order' => 1, 'is_default' => true],
        );

        return Document::create([
            'project_id' => $project->id,
            'category_id' => $category->id,
            'owner_id' => $owner->id,
            'title' => $title,
            'description' => 'Private document metadata only.',
            'status' => Document::STATUS_DRAFT,
            'visibility' => Document::VISIBILITY_PRIVATE,
        ]);
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
