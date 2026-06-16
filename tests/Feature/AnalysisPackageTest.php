<?php

namespace Tests\Feature;

use App\Models\AnalysisDocumentPackage;
use App\Models\AnalysisSynthesisItem;
use App\Models\ExpertValidator;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyDistributionBatch;
use App\Models\SurveyDistributionRecipient;
use App\Models\SurveyQuestion;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyReadabilityResponse;
use App\Models\SurveyReadabilityRound;
use App\Models\SurveyResponse;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRecommendation;
use App\Models\SurveyValidationRound;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateLecturerNeedsAnalysisQuestionnaireAction;
use App\Modules\Surveys\Actions\CreatePractitionerInterviewFormAction;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Support\SurveyIntroTemplates;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_analysis_package_with_default_metadata(): void
    {
        [$admin, $survey] = $this->surveyFixture();

        $this->actingAs($admin)
            ->get(route('admin.surveys.analysis-package.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Official Analysis Instrument Package')
            ->assertSeeText('Paket Instrumen dan Laporan Tahap Analysis PharmVR')
            ->assertSeeText('PHARMVR-ADDIE-ANALYSIS')
            ->assertSeeText('Universitas Padjadjaran')
            ->assertSeeText('Program Studi Doktor Ilmu Farmasi')
            ->assertSeeText('Supervisor Review Notes')
            ->assertDontSee('token_hash');

        $this->assertDatabaseHas('analysis_document_packages', [
            'survey_id' => $survey->id,
            'document_code' => 'PHARMVR-ADDIE-ANALYSIS',
            'version' => 'Draft v1',
        ]);
    }

    public function test_admin_can_update_package_metadata(): void
    {
        [$admin, $survey] = $this->surveyFixture();
        $this->actingAs($admin)->get(route('admin.surveys.analysis-package.index', ['survey' => $survey]));

        $this->actingAs($admin)
            ->put(route('admin.surveys.analysis-package.update', ['survey' => $survey]), [
                'title' => 'Paket Review Analysis PharmVR Resmi',
                'document_code' => 'PV-AN-001',
                'version' => 'Draft v2',
                'document_date' => today()->toDateString(),
                'researcher_name' => 'Farhamzah',
                'researcher_identifier' => 'NPM-001',
                'institution' => 'Universitas Padjadjaran',
                'study_program' => 'Doktor Ilmu Farmasi',
                'promoter_name' => 'Ketua Promotor',
                'co_promoter_names' => "Co-Promotor 1\nCo-Promotor 2",
                'stage' => 'ADDIE Analysis',
                'status' => AnalysisDocumentPackage::STATUS_REVIEWED,
                'purpose_text' => 'Purpose text updated.',
                'notes' => 'Ready for supervisor discussion.',
            ])
            ->assertRedirect(route('admin.surveys.analysis-package.index', ['survey' => $survey]));

        $this->assertDatabaseHas('analysis_document_packages', [
            'survey_id' => $survey->id,
            'title' => 'Paket Review Analysis PharmVR Resmi',
            'status' => AnalysisDocumentPackage::STATUS_REVIEWED,
            'researcher_identifier' => 'NPM-001',
        ]);
    }

    public function test_package_lists_instruments_summaries_distribution_collection_and_synthesis(): void
    {
        [$admin, $survey] = $this->surveyFixture();
        $lecturer = app(CreateLecturerNeedsAnalysisQuestionnaireAction::class)->handle($admin, $survey);
        $practitioner = app(CreatePractitionerInterviewFormAction::class)->handle($admin, $survey->fresh());
        $this->submitResponse($survey);
        $this->submitResponse($lecturer);
        $this->submitResponse($practitioner);
        $this->submittedValidationFixture($admin, $survey);
        $this->submittedReadabilityFixture($admin, $survey);
        $this->distributionFixture($admin, $survey);
        AnalysisSynthesisItem::create($this->synthesisPayload($survey));

        $this->actingAs($admin)
            ->get(route('admin.surveys.analysis-package.index', ['survey' => $survey->fresh()]))
            ->assertOk()
            ->assertSeeText('Student Questionnaire Instrument')
            ->assertSeeText('Lecturer Questionnaire Instrument')
            ->assertSeeText('Practitioner Interview Form')
            ->assertSeeText('Kuesioner Analisis Kebutuhan Dosen PharmVR')
            ->assertSeeText('Pedoman Wawancara Praktisi/Ahli CPOB PharmVR')
            ->assertSeeText('Expert Validation Summary')
            ->assertSeeText('Readability Test Summary')
            ->assertSeeText('Distribution Summary')
            ->assertSeeText('Collection Monitoring Summary')
            ->assertSeeText('Synthesis Matrix')
            ->assertSeeText('PharmVR membutuhkan prioritas MVP berbasis evidence.')
            ->assertSeeText('Readiness Recommendation')
            ->assertSeeText('Signature / Approval Placeholder')
            ->assertDontSee('token_hash');
    }

    public function test_print_html_doc_exports_and_finalize_work(): void
    {
        [$admin, $survey] = $this->surveyFixture();
        AnalysisSynthesisItem::create($this->synthesisPayload($survey));

        $this->actingAs($admin)
            ->get(route('admin.surveys.analysis-package.print', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Collection Monitoring Summary')
            ->assertSeeText('Print to PDF');

        $this->actingAs($admin)
            ->get(route('admin.surveys.analysis-package.export-html', ['survey' => $survey]))
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8');

        $this->actingAs($admin)
            ->get(route('admin.surveys.analysis-package.export-doc', ['survey' => $survey]))
            ->assertOk()
            ->assertHeader('content-type', 'application/msword; charset=UTF-8');

        $this->actingAs($admin)
            ->post(route('admin.surveys.analysis-package.finalize', ['survey' => $survey]))
            ->assertRedirect(route('admin.surveys.analysis-package.index', ['survey' => $survey]));

        $package = AnalysisDocumentPackage::where('survey_id', $survey->id)->firstOrFail();
        $this->assertSame(AnalysisDocumentPackage::STATUS_FINAL, $package->status);
        $this->assertNotNull($package->finalized_at);
        $this->assertIsArray($package->snapshot_json);
        $this->assertSame('Not Ready', $package->snapshot_json['readiness_status']);
    }

    public function test_package_routes_are_authenticated_and_project_authorized(): void
    {
        [$admin, $survey] = $this->surveyFixture();
        $other = User::factory()->create();
        $other->assignRole('admin');

        $this->get(route('admin.surveys.analysis-package.index', ['survey' => $survey]))
            ->assertRedirect('/admin/login');

        $this->actingAs($other)
            ->get(route('admin.surveys.analysis-package.index', ['survey' => $survey]))
            ->assertForbidden();
    }

    public function test_existing_analysis_distribution_collection_pages_still_load_with_package_link(): void
    {
        [$admin, $survey] = $this->surveyFixture();

        $this->actingAs($admin)
            ->get(route('admin.surveys.analysis.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Analysis Package');

        $this->actingAs($admin)
            ->get(route('admin.surveys.distribution.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Analysis Package');

        $this->actingAs($admin)
            ->get(route('admin.surveys.collection-monitoring.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Analysis Package');
    }

    /**
     * @return array{0: User, 1: Survey}
     */
    private function surveyFixture(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create(['name' => 'Researcher Admin']);
        $admin->assignRole('admin');

        $project = ResearchProject::create([
            'owner_id' => $admin->id,
            'title' => 'PharmVR ADDIE Research',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        $survey = app(CreateSurveyAction::class)->handle($admin, $project, [
            'title' => 'Analisis Kebutuhan Mahasiswa PharmVR',
            'description' => 'Student needs analysis questionnaire for PharmVR.',
            'identity_mode' => Survey::IDENTITY_ANONYMOUS,
            'instrument_type' => Survey::INSTRUMENT_ANALYSIS_STUDENT,
            'analysis_group_key' => Survey::ANALYSIS_GROUP_PHARMVR_ADDIE,
            ...SurveyIntroTemplates::studentPharmVr(),
        ]);

        $page = $survey->pages()->create([
            'title' => 'Kebutuhan Pembelajaran',
            'description' => 'Bagian kebutuhan mahasiswa.',
            'sort_order' => 1,
        ]);

        $survey->questions()->create([
            'page_id' => $page->id,
            'question_key' => 'student_need',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Mahasiswa membutuhkan PharmVR untuk memahami CPOB.',
            'options' => ['scale' => [1, 2, 3, 4, 5]],
            'is_required' => true,
            'sort_order' => 1,
        ]);

        return [$admin, $survey->fresh()];
    }

    private function submitResponse(Survey $survey): void
    {
        SurveyResponse::create([
            'survey_id' => $survey->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'metadata' => ['identity_mode' => $survey->identity_mode],
        ]);
    }

    private function submittedValidationFixture(User $admin, Survey $survey): void
    {
        $round = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $survey->project_id,
            'created_by' => $admin->id,
            'title' => 'Validasi Ahli Instrumen',
            'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
            'rating_scale_min' => 1,
            'rating_scale_max' => 5,
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);

        $validator = ExpertValidator::create([
            'created_by' => $admin->id,
            'name' => 'Validator CPOB',
            'is_active' => true,
        ]);

        $assignment = SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->id,
            'expert_validator_id' => $validator->id,
            'role' => 'content_expert',
            'status' => SurveyValidationAssignment::STATUS_PENDING,
            'created_by' => $admin->id,
        ]);

        SurveyValidationRecommendation::create([
            'survey_validation_assignment_id' => $assignment->id,
            'survey_id' => $survey->id,
            'overall_score' => 4.5,
            'feasibility_decision' => SurveyValidationRecommendation::DECISION_VALID_WITH_MINOR_REVISION,
            'general_comments' => 'Instrumen layak digunakan.',
            'revision_suggestions' => 'Perjelas instruksi.',
        ]);

        $assignment->markSubmitted();
    }

    private function submittedReadabilityFixture(User $admin, Survey $survey): void
    {
        $round = SurveyReadabilityRound::create([
            'survey_id' => $survey->id,
            'created_by' => $admin->id,
            'title' => 'Uji Keterbacaan Awal',
            'status' => SurveyReadabilityRound::STATUS_OPEN,
            'target_participants' => 5,
        ]);

        $participant = SurveyReadabilityParticipant::create([
            'survey_readability_round_id' => $round->id,
            'survey_id' => $survey->id,
            'participant_name' => 'Pilot Student',
            'participant_type' => SurveyReadabilityParticipant::TYPE_STUDENT,
            'status' => SurveyReadabilityParticipant::STATUS_PENDING,
        ]);

        SurveyReadabilityResponse::create([
            'survey_readability_participant_id' => $participant->id,
            'survey_readability_round_id' => $round->id,
            'survey_id' => $survey->id,
            'overall_clarity_score' => 4,
            'overall_length_score' => 4,
            'terminology_clarity_score' => 4,
            'answer_option_clarity_score' => 4,
            'instruction_clarity_score' => 4,
            'estimated_completion_minutes' => 12,
            'has_confusing_items' => false,
            'final_decision' => SurveyReadabilityResponse::DECISION_EASY,
        ]);

        $participant->markSubmitted();
    }

    private function distributionFixture(User $admin, Survey $survey): void
    {
        $batch = SurveyDistributionBatch::create([
            'survey_id' => $survey->id,
            'project_id' => $survey->project_id,
            'audience_type' => SurveyDistributionBatch::AUDIENCE_STUDENT,
            'title' => 'Mahasiswa Batch 1',
            'deadline' => today()->addWeek(),
            'status' => SurveyDistributionBatch::STATUS_SENT_MANUALLY,
            'created_by' => $admin->id,
            'notes' => 'Shared via class group.',
        ]);

        SurveyDistributionRecipient::create([
            'batch_id' => $batch->id,
            'target_survey_id' => $survey->id,
            'name' => 'Student A',
            'status' => SurveyDistributionRecipient::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function synthesisPayload(Survey $survey): array
    {
        return [
            'survey_id' => $survey->id,
            'project_id' => $survey->project_id,
            'source_type' => AnalysisSynthesisItem::SOURCE_MANUAL,
            'source_label' => 'Researcher memo',
            'theme' => AnalysisSynthesisItem::THEME_SCENE_PRIORITY,
            'finding' => 'PharmVR membutuhkan prioritas MVP berbasis evidence.',
            'evidence_summary' => 'Manual synthesis from ADDIE Analysis data.',
            'evidence_metric' => 'Researcher reviewed',
            'priority_level' => AnalysisSynthesisItem::PRIORITY_HIGH,
            'design_implication' => 'Scene perlu masuk rancangan MVP.',
            'development_decision' => 'Masukkan ke backlog Development.',
            'mapped_module' => 'Gowning',
            'status' => AnalysisSynthesisItem::STATUS_ACCEPTED,
            'researcher_note' => 'Catatan peneliti.',
        ];
    }
}
