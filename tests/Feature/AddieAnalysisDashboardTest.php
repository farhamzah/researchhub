<?php

namespace Tests\Feature;

use App\Models\AnalysisSynthesisItem;
use App\Models\ExpertValidator;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyReadabilityQuestionFeedback;
use App\Models\SurveyReadabilityResponse;
use App\Models\SurveyReadabilityRound;
use App\Models\SurveyResponse;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRecommendation;
use App\Models\SurveyValidationRound;
use App\Models\SurveyValidationScore;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddieAnalysisDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_analysis_dashboard_with_empty_state(): void
    {
        [$owner, $survey] = $this->surveyFixture();

        $this->actingAs($owner)
            ->get(route('admin.surveys.analysis.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('ADDIE Analysis Dashboard')
            ->assertSeeText('Not Ready')
            ->assertSeeText('Belum ada synthesis item')
            ->assertSeeText('No descriptive analysis result yet');
    }

    public function test_dashboard_summarizes_survey_responses_and_priorities(): void
    {
        [$owner, $survey, $difficultyQuestion, $featureQuestion] = $this->surveyFixture();
        $this->submitMainResponse($survey, [
            $difficultyQuestion->question_key => 5,
            $featureQuestion->question_key => ['Lobby', 'Gowning'],
        ]);
        $this->submitMainResponse($survey, [
            $difficultyQuestion->question_key => 4,
            $featureQuestion->question_key => ['Gowning'],
        ]);

        $this->actingAs($owner)
            ->get(route('admin.surveys.analysis.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Main Survey Responses')
            ->assertSeeText('Mean 4.50')
            ->assertSeeText('Prioritas Fitur PharmVR')
            ->assertSeeText('Gowning')
            ->assertSeeText('Top Learning Difficulties');
    }

    public function test_dashboard_integrates_expert_validation_and_readability_results(): void
    {
        [$owner, $survey] = $this->surveyFixture();
        $this->seedSubmittedValidation($owner, $survey);
        $this->seedSubmittedReadability($owner, $survey);

        $this->actingAs($owner)
            ->get(route('admin.surveys.analysis.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Ringkasan Validasi Ahli')
            ->assertSeeText('Very feasible / very valid')
            ->assertSeeText('Ringkasan Uji Keterbacaan')
            ->assertSeeText('Readable with minor revision');
    }

    public function test_admin_can_create_update_and_delete_synthesis_item(): void
    {
        [$owner, $survey] = $this->surveyFixture();

        $this->actingAs($owner)
            ->post(route('admin.surveys.analysis.synthesis-items.store', ['survey' => $survey]), $this->synthesisPayload([
                'finding' => 'Mahasiswa membutuhkan scene Gowning sebagai prioritas awal.',
            ]))
            ->assertRedirect(route('admin.surveys.analysis.index', ['survey' => $survey]));

        $item = AnalysisSynthesisItem::query()->firstOrFail();

        $this->assertDatabaseHas('analysis_synthesis_items', [
            'survey_id' => $survey->id,
            'finding' => 'Mahasiswa membutuhkan scene Gowning sebagai prioritas awal.',
        ]);

        $this->actingAs($owner)
            ->put(route('admin.surveys.analysis.synthesis-items.update', ['survey' => $survey, 'synthesisItem' => $item]), $this->synthesisPayload([
                'finding' => 'Mahasiswa membutuhkan scene Gowning dan Hygiene sebagai prioritas awal.',
                'status' => AnalysisSynthesisItem::STATUS_ACCEPTED,
            ]))
            ->assertRedirect(route('admin.surveys.analysis.index', ['survey' => $survey]));

        $this->assertDatabaseHas('analysis_synthesis_items', [
            'id' => $item->id,
            'status' => AnalysisSynthesisItem::STATUS_ACCEPTED,
        ]);

        $this->actingAs($owner)
            ->delete(route('admin.surveys.analysis.synthesis-items.delete', ['survey' => $survey, 'synthesisItem' => $item]))
            ->assertRedirect(route('admin.surveys.analysis.index', ['survey' => $survey]));

        $this->assertDatabaseMissing('analysis_synthesis_items', [
            'id' => $item->id,
        ]);
    }

    public function test_generate_draft_synthesis_creates_proposed_items_without_duplicates(): void
    {
        [$owner, $survey, $difficultyQuestion, $featureQuestion] = $this->surveyFixture();
        $this->submitMainResponse($survey, [
            $difficultyQuestion->question_key => 5,
            $featureQuestion->question_key => ['Lobby', 'Gowning'],
        ]);
        $this->seedSubmittedValidation($owner, $survey);
        $this->seedSubmittedReadability($owner, $survey);

        $this->actingAs($owner)
            ->post(route('admin.surveys.analysis.generate-synthesis', ['survey' => $survey]))
            ->assertRedirect(route('admin.surveys.analysis.index', ['survey' => $survey]));

        $firstCount = AnalysisSynthesisItem::count();
        $this->assertGreaterThan(0, $firstCount);
        $this->assertDatabaseHas('analysis_synthesis_items', [
            'survey_id' => $survey->id,
            'status' => AnalysisSynthesisItem::STATUS_PROPOSED,
        ]);

        $this->actingAs($owner)
            ->post(route('admin.surveys.analysis.generate-synthesis', ['survey' => $survey]))
            ->assertRedirect(route('admin.surveys.analysis.index', ['survey' => $survey]));

        $this->assertSame($firstCount, AnalysisSynthesisItem::count());
    }

    public function test_printable_analysis_report_loads(): void
    {
        [$owner, $survey] = $this->surveyFixture();
        AnalysisSynthesisItem::create($this->synthesisPayload([
            'survey_id' => $survey->id,
            'project_id' => $survey->project_id,
            'finding' => 'PharmVR membutuhkan prioritas MVP berbasis evidence.',
            'status' => AnalysisSynthesisItem::STATUS_ACCEPTED,
        ]));

        $this->actingAs($owner)
            ->get(route('admin.surveys.analysis.report', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('MyRiset ADDIE Analysis Report')
            ->assertSeeText('Synthesis Matrix')
            ->assertSeeText('PharmVR membutuhkan prioritas MVP berbasis evidence.');
    }

    public function test_existing_public_survey_validation_and_readability_routes_still_work(): void
    {
        [$owner, $survey] = $this->surveyFixture();
        app(PublishSurveyAction::class)->handle($owner, $survey);
        $assignment = $this->seedValidationAssignment($owner, $survey, 'public-validation-token');
        $participant = $this->seedReadabilityParticipant($owner, $survey, 'public-readability-token');

        $this->get(route('survey.show', ['survey' => $survey->slug]))
            ->assertOk()
            ->assertSee($survey->title);

        $this->get(route('validation.survey.show', ['token' => 'public-validation-token']))
            ->assertOk()
            ->assertSeeText('Validasi Ahli Instrumen');

        $this->get(route('readability.survey.show', ['token' => 'public-readability-token']))
            ->assertOk()
            ->assertSeeText('Uji Keterbacaan Instrumen');

        $this->assertTrue($assignment->fresh()->isAccessible());
        $this->assertTrue($participant->fresh()->isAccessible());
    }

    /**
     * @return array{0: User, 1: Survey, 2: SurveyQuestion, 3: SurveyQuestion}
     */
    private function surveyFixture(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = User::factory()->create();
        $owner->assignRole('admin');

        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Disertasi S3',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Analisa Sistem',
            'description' => 'Student needs analysis questionnaire for PharmVR Analysis ADDIE stage.',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);

        $difficultyQuestion = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'kesulitan_cpob',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Kesulitan memahami konsep CPOB GMP',
            'options' => ['scale' => [1, 2, 3, 4, 5]],
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $featureQuestion = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'fitur_pharmvr',
            'type' => SurveyQuestion::TYPE_MULTIPLE_CHOICE,
            'label' => 'Fitur PharmVR yang diharapkan',
            'options' => ['choices' => ['Lobby', 'Gowning', 'Hygiene', 'Dashboard progress']],
            'is_required' => true,
            'sort_order' => 2,
        ]);

        return [$owner, $survey->fresh(), $difficultyQuestion, $featureQuestion];
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function submitMainResponse(Survey $survey, array $answers): void
    {
        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'metadata' => ['identity_mode' => $survey->identity_mode],
        ]);

        foreach ($answers as $questionKey => $value) {
            $question = $survey->questions()->where('question_key', $questionKey)->firstOrFail();
            SurveyAnswer::create([
                'survey_response_id' => $response->id,
                'survey_question_id' => $question->id,
                'question_key' => $questionKey,
                'answer_value' => $value,
            ]);
        }
    }

    private function seedSubmittedValidation(User $owner, Survey $survey): void
    {
        $assignment = $this->seedValidationAssignment($owner, $survey);

        foreach ($survey->questions as $question) {
            SurveyValidationScore::create([
                'survey_validation_assignment_id' => $assignment->id,
                'survey_question_id' => $question->id,
                'content_relevance_score' => 5,
                'language_clarity_score' => 4,
                'construct_alignment_score' => 5,
                'measurability_score' => 4,
                'feasibility_score' => 5,
                'ethical_suitability_score' => 5,
                'comment' => 'Instrumen relevan untuk PharmVR.',
                'recommendation' => SurveyValidationScore::RECOMMENDATION_ACCEPTED,
            ]);
        }

        SurveyValidationRecommendation::create([
            'survey_validation_assignment_id' => $assignment->id,
            'survey_id' => $survey->id,
            'overall_score' => 4.67,
            'feasibility_decision' => SurveyValidationRecommendation::DECISION_VALID_WITH_MINOR_REVISION,
            'general_comments' => 'Valid untuk digunakan.',
            'revision_suggestions' => 'Perbaiki beberapa istilah.',
        ]);

        $assignment->markSubmitted();
    }

    private function seedValidationAssignment(User $owner, Survey $survey, ?string $token = null): SurveyValidationAssignment
    {
        $validator = ExpertValidator::create([
            'created_by' => $owner->id,
            'name' => 'Validator Ahli',
            'is_active' => true,
        ]);
        $round = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $survey->project_id,
            'created_by' => $owner->id,
            'title' => 'Expert Validation Round',
            'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
            'rating_scale_min' => 1,
            'rating_scale_max' => 5,
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);

        return SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->id,
            'expert_validator_id' => $validator->id,
            'role' => 'content_expert',
            'status' => $token ? SurveyValidationAssignment::STATUS_LINK_GENERATED : SurveyValidationAssignment::STATUS_PENDING,
            'token_hash' => $token ? SurveyValidationAssignment::hashToken($token) : null,
            'token_created_at' => $token ? now() : null,
            'created_by' => $owner->id,
        ]);
    }

    private function seedSubmittedReadability(User $owner, Survey $survey): void
    {
        $participant = $this->seedReadabilityParticipant($owner, $survey);
        $round = $participant->round;
        $response = SurveyReadabilityResponse::create([
            'survey_readability_participant_id' => $participant->id,
            'survey_readability_round_id' => $round->id,
            'survey_id' => $survey->id,
            'overall_clarity_score' => 4,
            'overall_length_score' => 4,
            'terminology_clarity_score' => 3,
            'answer_option_clarity_score' => 4,
            'instruction_clarity_score' => 4,
            'estimated_completion_minutes' => 12,
            'has_confusing_items' => true,
            'confusing_items' => 'Istilah CPOB perlu contoh.',
            'general_comments' => 'Instrumen mudah dipahami.',
            'revision_suggestions' => 'Tambahkan contoh.',
            'final_decision' => SurveyReadabilityResponse::DECISION_MINOR_REVISION,
        ]);

        SurveyReadabilityQuestionFeedback::create([
            'survey_readability_response_id' => $response->id,
            'survey_question_id' => $survey->questions()->firstOrFail()->id,
            'issue_type' => SurveyReadabilityQuestionFeedback::ISSUE_DIFFICULT_TERM,
            'comment' => 'Istilah terlalu teknis.',
        ]);

        $participant->forceFill([
            'status' => SurveyReadabilityParticipant::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ])->save();
    }

    private function seedReadabilityParticipant(User $owner, Survey $survey, ?string $token = null): SurveyReadabilityParticipant
    {
        $round = SurveyReadabilityRound::create([
            'survey_id' => $survey->id,
            'created_by' => $owner->id,
            'title' => 'Readability Round',
            'status' => SurveyReadabilityRound::STATUS_OPEN,
            'target_participants' => 10,
        ]);

        return SurveyReadabilityParticipant::create([
            'survey_readability_round_id' => $round->id,
            'survey_id' => $survey->id,
            'participant_name' => 'Pilot Student',
            'participant_type' => SurveyReadabilityParticipant::TYPE_STUDENT,
            'status' => $token ? SurveyReadabilityParticipant::STATUS_PENDING : SurveyReadabilityParticipant::STATUS_OPENED,
            'token_hash' => $token ? SurveyReadabilityParticipant::hashToken($token) : null,
            'token_created_at' => $token ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function synthesisPayload(array $overrides = []): array
    {
        return [
            'source_type' => AnalysisSynthesisItem::SOURCE_MANUAL,
            'source_label' => 'Researcher memo',
            'theme' => AnalysisSynthesisItem::THEME_SCENE_PRIORITY,
            'finding' => 'Mahasiswa membutuhkan prioritas scene PharmVR.',
            'evidence_summary' => 'Manual synthesis from ADDIE Analysis data.',
            'evidence_metric' => 'Researcher reviewed',
            'priority_level' => AnalysisSynthesisItem::PRIORITY_HIGH,
            'design_implication' => 'Scene perlu masuk rancangan MVP.',
            'development_decision' => 'Masukkan ke backlog Development.',
            'mapped_module' => 'Gowning',
            'status' => AnalysisSynthesisItem::STATUS_PROPOSED,
            'researcher_note' => 'Catatan peneliti.',
            ...$overrides,
        ];
    }
}
