<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ExpertValidator;
use App\Models\ResearchProject;
use App\Models\Respondent;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\SurveyValidationScore;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyValidationResultPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_view_validation_results_without_token_or_respondent_leakage(): void
    {
        [$owner, $survey, $round] = $this->resultFixture();

        $this->actingAs($owner)
            ->get(route('admin.surveys.validation.results.show', ['survey' => $survey, 'round' => $round]))
            ->assertOk()
            ->assertSee('Expert Validation Results')
            ->assertSee("Average Aiken's V", false)
            ->assertSee('S-CVI/Ave')
            ->assertSee('Item-Level Results')
            ->assertSee('Comments and Recommendations')
            ->assertSee('Copy-Ready Narrative')
            ->assertSee('Results are preliminary because not all validators have submitted.')
            ->assertSee('Butir pertama')
            ->assertSee('Komentar validasi')
            ->assertSee('Aiken&#039;s V sebesar', false)
            ->assertDontSee('token-hash-secret')
            ->assertDontSee('/validation/survey/')
            ->assertDontSee('Respondent Rahasia')
            ->assertDontSee('secret@example.test');

        $log = ActivityLog::query()->where('action', 'survey_validation_results.viewed')->firstOrFail();
        $this->assertSame($round->id, $log->metadata['survey_validation_round_id']);
        $this->assertSame(1, $log->metadata['submitted_count']);
        $this->assertStringNotContainsString('Komentar validasi', json_encode($log->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('token-hash-secret', json_encode($log->metadata, JSON_THROW_ON_ERROR));
    }

    public function test_unauthorized_user_cannot_view_other_project_validation_results(): void
    {
        [, $survey, $round] = $this->resultFixture('owner@example.test');
        $outsider = $this->adminUser('outsider@example.test');

        $this->actingAs($outsider)
            ->get(route('admin.surveys.validation.results.show', ['survey' => $survey, 'round' => $round]))
            ->assertForbidden();
    }

    public function test_empty_result_page_renders_safe_no_submission_state(): void
    {
        [$owner, $survey, $round] = $this->emptyRoundFixture();

        $this->actingAs($owner)
            ->get(route('admin.surveys.validation.results.show', ['survey' => $survey, 'round' => $round]))
            ->assertOk()
            ->assertSee('No submitted expert validation yet.')
            ->assertSee('N/A')
            ->assertSee('CVR requires an explicit essential/not-essential expert judgment');
    }

    private function adminUser(string $email = 'admin@example.test'): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @return array{0: User, 1: Survey, 2: SurveyValidationRound}
     */
    private function resultFixture(string $email = 'admin@example.test'): array
    {
        [$owner, $survey, $round] = $this->emptyRoundFixture($email);
        $question = SurveyQuestion::query()->where('survey_id', $survey->id)->firstOrFail();

        $submittedAssignment = $this->assignment($round, $owner, 'Validator Submitted', SurveyValidationAssignment::STATUS_SUBMITTED, now());
        $this->assignment($round, $owner, 'Validator Pending', SurveyValidationAssignment::STATUS_LINK_GENERATED, null);

        SurveyValidationScore::create([
            'survey_validation_assignment_id' => $submittedAssignment->id,
            'survey_question_id' => $question->id,
            'relevance_score' => 4,
            'clarity_score' => 4,
            'language_score' => 4,
            'appropriateness_score' => 4,
            'comment' => 'Komentar validasi',
            'recommendation' => SurveyValidationScore::RECOMMENDATION_ACCEPTED,
        ]);

        $respondent = Respondent::create([
            'project_id' => $survey->project_id,
            'survey_id' => $survey->id,
            'name' => 'Respondent Rahasia',
            'email' => 'secret@example.test',
        ]);

        SurveyResponse::create([
            'survey_id' => $survey->id,
            'respondent_id' => $respondent->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return [$owner, $survey, $round];
    }

    /**
     * @return array{0: User, 1: Survey, 2: SurveyValidationRound}
     */
    private function emptyRoundFixture(string $email = 'admin@example.test'): array
    {
        $owner = $this->adminUser($email);
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Result Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = Survey::create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Instrumen Hasil Validasi',
            'status' => Survey::STATUS_DRAFT,
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);
        $round = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Result Round',
            'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
            'rating_scale_min' => 1,
            'rating_scale_max' => 4,
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);
        SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'item_1',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Butir pertama',
            'sort_order' => 1,
        ]);

        return [$owner, $survey, $round];
    }

    private function assignment(SurveyValidationRound $round, User $user, string $name, string $status, mixed $submittedAt): SurveyValidationAssignment
    {
        $validator = ExpertValidator::create([
            'created_by' => $user->id,
            'name' => $name,
            'is_active' => true,
        ]);

        return SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->id,
            'expert_validator_id' => $validator->id,
            'role' => 'content_expert',
            'status' => $status,
            'token_hash' => 'token-hash-secret-'.$name,
            'submitted_at' => $submittedAt,
            'created_by' => $user->id,
        ]);
    }
}
