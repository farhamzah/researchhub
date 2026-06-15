<?php

namespace Tests\Feature;

use App\Models\ExpertValidator;
use App\Models\ExpertValidatorProject;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRecommendation;
use App\Models\SurveyValidationRevision;
use App\Models\SurveyValidationRound;
use App\Models\SurveyValidationScore;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpertValidatorAssessmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expert_validator_submits_six_aspect_assessment_and_admin_reviews_report(): void
    {
        [$admin, $survey, $round, $assignment, $question, $token] = $this->fixture();

        $this->get(route('validator.surveys.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Content relevance')
            ->assertSee('Construct alignment')
            ->assertSee('Ethical/privacy suitability')
            ->assertDontSee('Dashboard')
            ->assertDontSee('Respondent Identity');

        $this->post(route('validator.surveys.store', ['token' => $token]), [
            'scores' => [
                $question->id => [
                    'content_relevance_score' => 5,
                    'language_clarity_score' => 4,
                    'construct_alignment_score' => 5,
                    'measurability_score' => 4,
                    'feasibility_score' => 5,
                    'ethical_suitability_score' => 5,
                    'comment' => 'Perjelas istilah PharmVR untuk responden.',
                    'recommendation' => SurveyValidationScore::RECOMMENDATION_MINOR_REVISION,
                ],
            ],
            'feasibility_decision' => SurveyValidationRecommendation::DECISION_VALID_WITH_MINOR_REVISION,
            'general_comments' => 'Instrumen layak digunakan setelah revisi kecil.',
            'revision_suggestions' => 'Tambahkan contoh singkat pada pengantar instrumen.',
        ])->assertRedirect(route('validation.survey.show', ['token' => $token]));

        $assignment->refresh();
        $this->assertSame(SurveyValidationAssignment::STATUS_SUBMITTED, $assignment->status);
        $this->assertNotNull($assignment->submitted_at);

        $this->assertDatabaseHas('survey_validation_scores', [
            'survey_validation_assignment_id' => $assignment->id,
            'survey_question_id' => $question->id,
            'content_relevance_score' => 5,
            'construct_alignment_score' => 5,
            'ethical_suitability_score' => 5,
        ]);
        $this->assertDatabaseHas('survey_validation_recommendations', [
            'survey_validation_assignment_id' => $assignment->id,
            'survey_id' => $survey->id,
            'feasibility_decision' => SurveyValidationRecommendation::DECISION_VALID_WITH_MINOR_REVISION,
        ]);
        $this->assertDatabaseHas('survey_validation_revisions', [
            'survey_id' => $survey->id,
            'source_assignment_id' => $assignment->id,
            'status' => SurveyValidationRevision::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.surveys.validation.results.show', ['survey' => $survey, 'round' => $round]))
            ->assertOk()
            ->assertSee('Feasibility Percentage')
            ->assertSee('Very feasible / very valid')
            ->assertSee('Valid with minor revision')
            ->assertSee('Revision Matrix')
            ->assertSee('Perjelas istilah PharmVR');

        $revision = SurveyValidationRevision::query()->where('survey_id', $survey->id)->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.surveys.validation.revisions.update', ['survey' => $survey, 'revision' => $revision]), [
                'revision_action' => 'Istilah PharmVR ditambahkan pada instruksi awal.',
                'status' => SurveyValidationRevision::STATUS_REVISED,
                'researcher_note' => 'Dimasukkan ke revisi instrumen v2.',
            ])
            ->assertRedirect(route('admin.surveys.validation.index', ['survey' => $survey]));

        $this->assertDatabaseHas('survey_validation_revisions', [
            'id' => $revision->id,
            'status' => SurveyValidationRevision::STATUS_REVISED,
            'revision_action' => 'Istilah PharmVR ditambahkan pada instruksi awal.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.surveys.validation.report', ['survey' => $survey, 'round' => $round]))
            ->assertOk()
            ->assertSee('MyRiset Expert Validator Assessment')
            ->assertSee('Aspect Summary')
            ->assertSee('Revision Matrix')
            ->assertDontSee($token);
    }

    /**
     * @return array{0: User, 1: Survey, 2: SurveyValidationRound, 3: SurveyValidationAssignment, 4: SurveyQuestion, 5: string}
     */
    private function fixture(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create(['email' => 'assessment-admin@example.test']);
        $admin->assignRole('admin');

        $project = ResearchProject::create([
            'owner_id' => $admin->id,
            'title' => 'PharmVR Analysis Stage',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        $survey = Survey::create([
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title' => 'Analisa Sistem PharmVR',
            'status' => Survey::STATUS_DRAFT,
            'identity_mode' => Survey::IDENTITY_HIDDEN,
            'is_public' => false,
        ]);

        $question = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'pharmvr_need_1',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Mahasiswa membutuhkan media PharmVR untuk memahami CPOB.',
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $round = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $project->id,
            'created_by' => $admin->id,
            'title' => 'ADDIE Analysis Expert Assessment',
            'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
            'rating_scale_min' => 1,
            'rating_scale_max' => 5,
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);

        $validator = ExpertValidator::create([
            'created_by' => $admin->id,
            'name' => 'Validator Instrumen',
            'email' => 'validator@example.test',
            'institution' => 'Universitas Contoh',
            'position' => 'Dosen Evaluasi Pendidikan',
            'expertise_areas' => ['Educational Evaluation / Instrument Expert'],
            'is_active' => true,
            'is_global' => false,
        ]);

        $token = 'safe-public-validator-token';
        $assignment = SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->id,
            'expert_validator_id' => $validator->id,
            'role' => ExpertValidatorProject::ROLE_CONTENT,
            'status' => SurveyValidationAssignment::STATUS_LINK_GENERATED,
            'token_hash' => SurveyValidationAssignment::hashToken($token),
            'token_created_at' => now(),
            'created_by' => $admin->id,
        ]);

        return [$admin, $survey, $round, $assignment, $question, $token];
    }
}
