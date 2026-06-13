<?php

namespace Tests\Feature;

use App\Models\ExpertValidator;
use App\Models\ExpertValidatorProject;
use App\Models\ResearchProject;
use App\Models\SupervisionReviewLink;
use App\Models\SupervisionSession;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicUxAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_validation_page_has_skip_link_standard_badges_and_safe_content(): void
    {
        [$token, $assignment] = $this->validationFixture();

        $this->get(route('validation.survey.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('data-ui="myriset-page-header"', false)
            ->assertSee('data-ui="myriset-section-card"', false)
            ->assertSee('data-ui="myriset-status-badge"', false)
            ->assertSee('for="relevance_score_item_1"', false)
            ->assertSeeText('Status: Aktif')
            ->assertDontSee($token)
            ->assertDontSee($assignment->token_hash)
            ->assertDontSee('/validation/survey/'.$token);
    }

    public function test_public_supervision_page_has_skip_link_standard_badges_and_safe_content(): void
    {
        [$token, $reviewLink] = $this->supervisionFixture();

        $this->get(route('supervision.review.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('data-ui="myriset-page-header"', false)
            ->assertSee('data-ui="myriset-section-card"', false)
            ->assertSee('data-ui="myriset-status-badge"', false)
            ->assertSee('for="decision"', false)
            ->assertSee('for="general_feedback"', false)
            ->assertSeeText('Status: Aktif')
            ->assertDontSee($token)
            ->assertDontSee($reviewLink->token_hash)
            ->assertDontSee('/supervision/review/'.$token);
    }

    /**
     * @return array{0: string, 1: SurveyValidationAssignment}
     */
    private function validationFixture(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = User::factory()->create(['email' => 'public-ux-validation-owner@example.test']);
        $owner->assignRole('admin');
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Public UX Validation Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = Survey::create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Public UX Validation Survey',
            'status' => Survey::STATUS_DRAFT,
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);
        SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'ux_accessibility_item',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Butir validasi memiliki label jelas',
            'sort_order' => 1,
        ]);
        $round = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Public UX Validation Round',
            'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
            'rating_scale_min' => 1,
            'rating_scale_max' => 4,
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);
        $validator = ExpertValidator::create([
            'created_by' => $owner->id,
            'name' => 'Public UX Validator',
            'email' => 'public-ux-validator@example.test',
            'is_active' => true,
        ]);
        $token = 'public-ux-validation-token';
        $assignment = SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->id,
            'expert_validator_id' => $validator->id,
            'role' => ExpertValidatorProject::ROLE_CONTENT,
            'status' => SurveyValidationAssignment::STATUS_LINK_GENERATED,
            'token_hash' => SurveyValidationAssignment::hashToken($token),
            'expires_at' => now()->addDays(3),
            'created_by' => $owner->id,
        ]);

        return [$token, $assignment];
    }

    /**
     * @return array{0: string, 1: SupervisionReviewLink}
     */
    private function supervisionFixture(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = User::factory()->create(['email' => 'public-ux-supervision-owner@example.test']);
        $owner->assignRole('admin');
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Public UX Supervision Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $session = SupervisionSession::create([
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Public UX Supervision Session',
            'meeting_type' => SupervisionSession::MEETING_REGULAR_GUIDANCE,
            'status' => SupervisionSession::STATUS_SHARED,
            'agenda' => 'Review UI consistency.',
            'progress_report' => 'Main flow is ready.',
            'questions' => 'Are public forms readable?',
            'requested_feedback' => 'Please review accessibility.',
            'next_plan' => 'Apply safe polish.',
        ]);
        $validator = ExpertValidator::create([
            'created_by' => $owner->id,
            'name' => 'Public UX Supervisor',
            'email' => 'public-ux-supervisor@example.test',
            'is_active' => true,
        ]);
        $token = 'public-ux-supervision-token';
        $reviewLink = SupervisionReviewLink::create([
            'supervision_session_id' => $session->id,
            'expert_validator_id' => $validator->id,
            'created_by' => $owner->id,
            'recipient_role' => 'Promotor',
            'status' => SupervisionReviewLink::STATUS_LINK_GENERATED,
            'token_hash' => SupervisionReviewLink::hashToken($token),
            'expires_at' => now()->addDays(3),
        ]);

        return [$token, $reviewLink];
    }
}
