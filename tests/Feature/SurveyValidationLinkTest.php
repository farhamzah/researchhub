<?php

namespace Tests\Feature;

use App\Filament\Resources\Surveys\Pages\ManageSurveys;
use App\Models\ActivityLog;
use App\Models\ExpertValidator;
use App\Models\ExpertValidatorProject;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\SurveyValidationScore;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SurveyValidationLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_validation_round_and_survey_action_is_visible(): void
    {
        [$admin, $project, $survey] = $this->surveyFixture();

        Livewire::actingAs($admin)
            ->test(ManageSurveys::class)
            ->assertTableActionVisible('validation', $survey)
            ->assertTableActionHasUrl('validation', route('admin.surveys.validation.index', ['survey' => $survey]), $survey);

        $this->actingAs($admin)
            ->get(route('admin.surveys.validation.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee('Survey Expert Validation')
            ->assertSee($survey->title)
            ->assertSee('Create Validation Round');

        $this->actingAs($admin)
            ->post(route('admin.surveys.validation.rounds.store', ['survey' => $survey]), [
                'title' => 'Expert Judgment Round 1',
                'description' => 'First expert review.',
                'instructions' => 'Nilai setiap butir instrumen.',
                'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
                'rating_scale_min' => 1,
                'rating_scale_max' => 4,
                'status' => SurveyValidationRound::STATUS_OPEN,
            ])
            ->assertRedirect(route('admin.surveys.validation.index', ['survey' => $survey]));

        $this->assertDatabaseHas('survey_validation_rounds', [
            'survey_id' => $survey->id,
            'research_project_id' => $project->id,
            'title' => 'Expert Judgment Round 1',
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'survey_validation_round.created']);
    }

    public function test_unauthorized_user_cannot_manage_inaccessible_survey_validation(): void
    {
        [, , $survey] = $this->surveyFixture('Owner Survey', 'owner@example.test');
        $outsider = $this->adminUser('outsider@example.test');

        $this->actingAs($outsider)
            ->get(route('admin.surveys.validation.index', ['survey' => $survey]))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('admin.surveys.validation.rounds.store', ['survey' => $survey]), [
                'title' => 'Cross Scope Round',
                'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
                'rating_scale_min' => 1,
                'rating_scale_max' => 4,
                'status' => SurveyValidationRound::STATUS_OPEN,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_assign_project_validator_and_generate_safe_link(): void
    {
        [$admin, $project, $survey] = $this->surveyFixture();
        $round = $this->round($survey, $admin);
        $validator = $this->validator($admin, 'Project Validator');
        $privateOtherValidator = $this->validator($this->adminUser('other@example.test'), 'Other Private Validator');

        ExpertValidatorProject::create([
            'research_project_id' => $project->id,
            'expert_validator_id' => $validator->id,
            'role' => ExpertValidatorProject::ROLE_CONTENT,
            'status' => ExpertValidatorProject::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.surveys.validation.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee('Project Validator')
            ->assertDontSee('Other Private Validator');

        $this->actingAs($admin)
            ->post(route('admin.surveys.validation.assignments.store', ['survey' => $survey, 'round' => $round]), [
                'expert_validator_id' => $validator->id,
                'role' => ExpertValidatorProject::ROLE_CONTENT,
                'expires_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('admin.surveys.validation.index', ['survey' => $survey]));

        $assignment = SurveyValidationAssignment::query()
            ->where('survey_validation_round_id', $round->id)
            ->where('expert_validator_id', $validator->id)
            ->firstOrFail();

        $response = $this->actingAs($admin)
            ->post(route('admin.surveys.validation.assignments.generate-link', ['survey' => $survey, 'assignment' => $assignment]));

        $response
            ->assertRedirect(route('admin.surveys.validation.index', ['survey' => $survey]))
            ->assertSessionHas('generated_validation_url');

        $assignment->refresh();
        $generatedUrl = session('generated_validation_url');
        $this->assertNotNull($generatedUrl);
        $this->assertSame(SurveyValidationAssignment::STATUS_LINK_GENERATED, $assignment->status);
        $this->assertNotNull($assignment->token_hash);
        $this->assertStringNotContainsString($this->tokenFromUrl($generatedUrl), $assignment->token_hash);
        $this->assertDatabaseMissing('survey_validation_assignments', ['token_hash' => $this->tokenFromUrl($generatedUrl)]);

        $rawDatabaseRow = DB::table('survey_validation_assignments')->where('id', $assignment->id)->first();
        $this->assertNotSame($this->tokenFromUrl($generatedUrl), $rawDatabaseRow->token_hash);

        $linkLog = ActivityLog::query()->where('action', 'survey_validation_link.generated')->firstOrFail();
        $this->assertStringNotContainsString($this->tokenFromUrl($generatedUrl), json_encode($linkLog->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('/validation/survey/', json_encode($linkLog->metadata, JSON_THROW_ON_ERROR));

        $this->actingAs($admin)
            ->from(route('admin.surveys.validation.index', ['survey' => $survey]))
            ->post(route('admin.surveys.validation.assignments.store', ['survey' => $survey, 'round' => $round]), [
                'expert_validator_id' => $privateOtherValidator->id,
                'role' => ExpertValidatorProject::ROLE_CONTENT,
            ])
            ->assertSessionHasErrors('expert_validator_id');
    }

    public function test_public_validation_form_supports_open_submit_and_blocks_resubmission(): void
    {
        [$admin, , $survey] = $this->surveyFixture();
        [$firstQuestion, $secondQuestion] = $this->questions($survey);
        $assignment = $this->assignmentWithGeneratedLink($admin, $survey);
        $token = $this->tokenFromUrl(session('generated_validation_url'));

        $this->get(route('validation.survey.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('MyRiset Expert Validation')
            ->assertDontSee('ResearchHub Expert Validation')
            ->assertSee($survey->title)
            ->assertSee('Validator Ahli')
            ->assertSee($firstQuestion->label)
            ->assertSee($secondQuestion->label)
            ->assertSee('Relevansi')
            ->assertDontSee('Dashboard')
            ->assertDontSee('Respondent Identity')
            ->assertDontSee('Analysis');

        $assignment->refresh();
        $this->assertSame(SurveyValidationAssignment::STATUS_OPENED, $assignment->status);
        $this->assertNotNull($assignment->opened_at);

        $invalidPayload = $this->scorePayload([$firstQuestion, $secondQuestion], 5);

        $this->from(route('validation.survey.show', ['token' => $token]))
            ->post(route('validation.survey.store', ['token' => $token]), $invalidPayload)
            ->assertSessionHasErrors();

        $this->post(route('validation.survey.store', ['token' => $token]), $this->scorePayload([$firstQuestion, $secondQuestion]))
            ->assertRedirect(route('validation.survey.show', ['token' => $token]));

        $assignment->refresh();
        $this->assertSame(SurveyValidationAssignment::STATUS_SUBMITTED, $assignment->status);
        $this->assertNotNull($assignment->submitted_at);
        $this->assertSame(2, SurveyValidationScore::where('survey_validation_assignment_id', $assignment->id)->count());

        $this->get(route('validation.survey.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Terima kasih')
            ->assertDontSee($firstQuestion->label);

        $this->post(route('validation.survey.store', ['token' => $token]), $this->scorePayload([$firstQuestion, $secondQuestion]))
            ->assertOk()
            ->assertSee('Terima kasih');

        $submitLog = ActivityLog::query()->where('action', 'survey_validation_assignment.submitted')->firstOrFail();
        $this->assertStringNotContainsString('Komentar validator', json_encode($submitLog->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($token, json_encode($submitLog->metadata, JSON_THROW_ON_ERROR));
    }

    public function test_invalid_expired_and_revoked_validation_tokens_are_safe(): void
    {
        [$admin, , $survey] = $this->surveyFixture();
        $this->questions($survey);

        $this->get(route('validation.survey.show', ['token' => 'invalid-token']))
            ->assertNotFound();

        $expiredAssignment = $this->assignmentWithGeneratedLink($admin, $survey, expiresAt: now()->subMinute());
        $expiredToken = $this->tokenFromUrl(session('generated_validation_url'));

        $this->get(route('validation.survey.show', ['token' => $expiredToken]))
            ->assertForbidden()
            ->assertSee('Link validasi tidak tersedia');

        $expiredAssignment->refresh();
        $this->assertSame(SurveyValidationAssignment::STATUS_EXPIRED, $expiredAssignment->status);

        $revokedAssignment = $this->assignmentWithGeneratedLink($admin, $survey);
        $revokedToken = $this->tokenFromUrl(session('generated_validation_url'));

        $this->actingAs($admin)
            ->post(route('admin.surveys.validation.assignments.revoke-link', ['survey' => $survey, 'assignment' => $revokedAssignment]))
            ->assertRedirect(route('admin.surveys.validation.index', ['survey' => $survey]));

        $this->get(route('validation.survey.show', ['token' => $revokedToken]))
            ->assertForbidden()
            ->assertSee('Link validasi tidak tersedia');

        $this->assertDatabaseHas('activity_logs', ['action' => 'survey_validation_link.revoked']);
    }

    private function adminUser(string $email = 'admin@example.test'): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create([
            'email' => $email,
        ]);
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: Survey}
     */
    private function surveyFixture(string $title = 'Validation Survey', string $email = 'admin@example.test'): array
    {
        $owner = $this->adminUser($email);
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Survey Validation Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = Survey::create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => $title,
            'status' => Survey::STATUS_DRAFT,
            'identity_mode' => Survey::IDENTITY_HIDDEN,
            'is_public' => false,
        ]);

        return [$owner, $project, $survey];
    }

    private function round(Survey $survey, User $user): SurveyValidationRound
    {
        return SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $survey->project_id,
            'created_by' => $user->id,
            'title' => 'Expert Round',
            'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
            'rating_scale_min' => 1,
            'rating_scale_max' => 4,
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);
    }

    private function validator(User $user, string $name = 'Validator Ahli'): ExpertValidator
    {
        return ExpertValidator::create([
            'created_by' => $user->id,
            'name' => $name,
            'institution' => 'Universitas Contoh',
            'is_active' => true,
            'is_global' => false,
        ]);
    }

    /**
     * @return array{0: SurveyQuestion, 1: SurveyQuestion}
     */
    private function questions(Survey $survey): array
    {
        return [
            SurveyQuestion::create([
                'survey_id' => $survey->id,
                'question_key' => 'item_1',
                'type' => SurveyQuestion::TYPE_LIKERT,
                'label' => 'Instrumen mudah dipahami',
                'is_required' => true,
                'sort_order' => 1,
            ]),
            SurveyQuestion::create([
                'survey_id' => $survey->id,
                'question_key' => 'item_2',
                'type' => SurveyQuestion::TYPE_SINGLE_CHOICE,
                'label' => 'Pilihan jawaban sesuai konteks',
                'is_required' => true,
                'sort_order' => 2,
            ]),
        ];
    }

    private function assignmentWithGeneratedLink(User $admin, Survey $survey, mixed $expiresAt = null): SurveyValidationAssignment
    {
        $round = $this->round($survey, $admin);
        $validator = $this->validator($admin);

        $assignment = SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->id,
            'expert_validator_id' => $validator->id,
            'role' => ExpertValidatorProject::ROLE_CONTENT,
            'status' => SurveyValidationAssignment::STATUS_PENDING,
            'expires_at' => $expiresAt,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.surveys.validation.assignments.generate-link', ['survey' => $survey, 'assignment' => $assignment]))
            ->assertRedirect(route('admin.surveys.validation.index', ['survey' => $survey]))
            ->assertSessionHas('generated_validation_url');

        return $assignment->refresh();
    }

    /**
     * @param  array<int, SurveyQuestion>  $questions
     * @return array<string, mixed>
     */
    private function scorePayload(array $questions, int $score = 4): array
    {
        $payload = ['scores' => []];

        foreach ($questions as $question) {
            $payload['scores'][$question->id] = [
                'relevance_score' => $score,
                'clarity_score' => $score,
                'language_score' => $score,
                'appropriateness_score' => $score,
                'comment' => 'Komentar validator',
                'recommendation' => SurveyValidationScore::RECOMMENDATION_MINOR_REVISION,
            ];
        }

        return $payload;
    }

    private function tokenFromUrl(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH) ?: '');
    }
}
