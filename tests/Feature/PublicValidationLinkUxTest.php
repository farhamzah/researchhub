<?php

namespace Tests\Feature;

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
use Tests\TestCase;

class PublicValidationLinkUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_validation_page_renders_polished_context_and_safe_item_cards(): void
    {
        [$token, $assignment, $survey, $round, $firstQuestion] = $this->validationFixture();

        $response = $this->get(route('validation.survey.show', ['token' => $token]));

        $response
            ->assertOk()
            ->assertSeeText('MyRiset Expert Validation')
            ->assertSeeText('Validasi Ahli Instrumen')
            ->assertSeeText($survey->project->title)
            ->assertSeeText($survey->title)
            ->assertSeeText($round->title)
            ->assertSeeText('Status: Aktif')
            ->assertSeeText('2 items need scoring')
            ->assertSeeText('Petunjuk validasi')
            ->assertSeeText('Kriteria dan skala')
            ->assertSeeText('1 = Tidak sesuai')
            ->assertSeeText('Relevansi')
            ->assertSeeText('Kejelasan')
            ->assertSeeText('Kebahasaan')
            ->assertSeeText('Kesesuaian')
            ->assertSeeText('Butir 1 dari 2')
            ->assertSeeText($firstQuestion->label)
            ->assertSeeText('Kirim Hasil Validasi')
            ->assertSee('min-h-11', false)
            ->assertDontSee($token)
            ->assertDontSee($assignment->token_hash)
            ->assertDontSee($assignment->id)
            ->assertDontSee($firstQuestion->id)
            ->assertDontSee('/validation/survey/'.$token)
            ->assertDontSee('validator.safe@example.test')
            ->assertDontSee('Private Respondent')
            ->assertDontSee('Drive folder');
    }

    public function test_public_validation_errors_are_item_specific_and_submitted_state_is_safe(): void
    {
        [$token, $assignment, , , $firstQuestion, $secondQuestion] = $this->validationFixture();

        $this->from(route('validation.survey.show', ['token' => $token]))
            ->post(route('validation.survey.store', ['token' => $token]), [
                'scores' => [
                    0 => [
                        'relevance_score' => 9,
                        'clarity_score' => 4,
                        'language_score' => 4,
                        'appropriateness_score' => 4,
                        'recommendation' => SurveyValidationScore::RECOMMENDATION_ACCEPTED,
                    ],
                ],
            ])
            ->assertSessionHasErrors();

        $this->get(route('validation.survey.show', ['token' => $token]))
            ->assertOk()
            ->assertSeeText('Butir 1: pilih skor relevansi')
            ->assertDontSee($assignment->token_hash);

        $this->post(route('validation.survey.store', ['token' => $token]), [
            'scores' => [
                0 => $this->scorePayload(),
                1 => $this->scorePayload(),
            ],
        ])->assertRedirect(route('validation.survey.show', ['token' => $token]));

        $this->get(route('validation.survey.show', ['token' => $token]))
            ->assertOk()
            ->assertSeeText('Terima kasih, hasil validasi telah dikirim.')
            ->assertSeeText('Masukan Bapak/Ibu akan digunakan untuk perbaikan instrumen penelitian.')
            ->assertDontSee($firstQuestion->label)
            ->assertDontSee($assignment->token_hash)
            ->assertDontSee($token);
    }

    public function test_public_validation_unavailable_state_is_generic_and_safe(): void
    {
        [$token, $assignment] = $this->validationFixture();

        $assignment->markRevoked();

        $this->get(route('validation.survey.show', ['token' => $token]))
            ->assertForbidden()
            ->assertSeeText('Link validasi tidak aktif.')
            ->assertSeeText('Silakan hubungi peneliti atau pengelola riset')
            ->assertDontSee($token)
            ->assertDontSee($assignment->token_hash)
            ->assertDontSeeText('Revoked');
    }

    /**
     * @return array{0: string, 1: SurveyValidationAssignment, 2: Survey, 3: SurveyValidationRound, 4: SurveyQuestion, 5: SurveyQuestion}
     */
    protected function validationFixture(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = User::factory()->create(['email' => 'public-validation-owner@example.test']);
        $owner->assignRole('admin');
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Public Validation UX Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = Survey::create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Instrumen UX Validasi',
            'status' => Survey::STATUS_DRAFT,
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);
        $firstQuestion = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'ux_item_1',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Butir instrumen mudah dipahami',
            'sort_order' => 1,
        ]);
        $secondQuestion = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'ux_item_2',
            'type' => SurveyQuestion::TYPE_SINGLE_CHOICE,
            'label' => 'Pilihan jawaban sesuai indikator',
            'sort_order' => 2,
        ]);
        SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'hidden_private',
            'type' => SurveyQuestion::TYPE_HIDDEN,
            'label' => 'Private Respondent',
            'sort_order' => 3,
        ]);
        $round = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Putaran Validasi UX',
            'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
            'rating_scale_min' => 1,
            'rating_scale_max' => 4,
            'status' => SurveyValidationRound::STATUS_OPEN,
            'instructions' => 'Mohon nilai tiap butir secara objektif.',
        ]);
        $validator = ExpertValidator::create([
            'created_by' => $owner->id,
            'name' => 'Validator Aman',
            'email' => 'validator.safe@example.test',
            'is_active' => true,
        ]);
        $token = 'public-validation-token-safe';
        $assignment = SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->id,
            'expert_validator_id' => $validator->id,
            'role' => ExpertValidatorProject::ROLE_CONTENT,
            'status' => SurveyValidationAssignment::STATUS_LINK_GENERATED,
            'token_hash' => SurveyValidationAssignment::hashToken($token),
            'expires_at' => now()->addDays(3),
            'created_by' => $owner->id,
        ]);

        return [$token, $assignment, $survey->fresh('project'), $round, $firstQuestion, $secondQuestion];
    }

    /**
     * @return array<string, mixed>
     */
    private function scorePayload(): array
    {
        return [
            'relevance_score' => 4,
            'clarity_score' => 4,
            'language_score' => 4,
            'appropriateness_score' => 4,
            'recommendation' => SurveyValidationScore::RECOMMENDATION_ACCEPTED,
            'comment' => 'Komentar aman',
        ];
    }
}
