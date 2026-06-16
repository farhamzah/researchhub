<?php

namespace Tests\Feature;

use App\Models\AnalysisCollectionTarget;
use App\Models\AnalysisSynthesisItem;
use App\Models\ExpertValidator;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyReadabilityRound;
use App\Models\SurveyResponse;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateLecturerNeedsAnalysisQuestionnaireAction;
use App\Modules\Surveys\Actions\CreatePractitionerInterviewFormAction;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Support\SurveyIntroTemplates;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisCollectionMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_collection_monitoring_page_with_default_targets(): void
    {
        [$admin, $survey] = $this->surveyFixture();

        $this->actingAs($admin)
            ->get(route('admin.surveys.collection-monitoring.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Analysis Data Collection Monitoring')
            ->assertSeeText('Student Questionnaire')
            ->assertSeeText('Lecturer Questionnaire')
            ->assertSeeText('Practitioner Interview Form')
            ->assertSeeText('Expert Validation')
            ->assertSeeText('Readability Test')
            ->assertSeeText('Synthesis Matrix')
            ->assertSeeText('30')
            ->assertSeeText('50')
            ->assertDontSee('token_hash');

        $this->assertDatabaseHas('analysis_collection_targets', [
            'survey_id' => $survey->id,
            'source_type' => AnalysisCollectionTarget::SOURCE_STUDENT_QUESTIONNAIRE,
            'minimum_count' => 30,
            'target_count' => 50,
        ]);
    }

    public function test_admin_can_update_collection_target(): void
    {
        [$admin, $survey] = $this->surveyFixture();
        $this->actingAs($admin)->get(route('admin.surveys.collection-monitoring.index', ['survey' => $survey]));
        $target = AnalysisCollectionTarget::where('survey_id', $survey->id)
            ->where('source_type', AnalysisCollectionTarget::SOURCE_STUDENT_QUESTIONNAIRE)
            ->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.surveys.collection-monitoring.targets.update', ['survey' => $survey, 'target' => $target]), [
                'minimum_count' => 12,
                'target_count' => 20,
                'due_date' => today()->addDays(10)->toDateString(),
                'notes' => 'Dissertation method decision.',
            ])
            ->assertRedirect(route('admin.surveys.collection-monitoring.index', ['survey' => $survey]));

        $this->assertDatabaseHas('analysis_collection_targets', [
            'id' => $target->id,
            'minimum_count' => 12,
            'target_count' => 20,
            'notes' => 'Dissertation method decision.',
        ]);
    }

    public function test_monitoring_calculates_counts_for_all_analysis_sources(): void
    {
        [$admin, $survey] = $this->surveyFixture();
        $lecturer = app(CreateLecturerNeedsAnalysisQuestionnaireAction::class)->handle($admin, $survey);
        $practitioner = app(CreatePractitionerInterviewFormAction::class)->handle($admin, $survey->fresh());
        $this->submitResponses($survey, 2);
        $this->submitResponses($lecturer, 1);
        $this->submitResponses($practitioner, 1);
        $this->validationFixture($admin, $survey, submitted: true);
        $this->readabilityFixture($admin, $survey, submitted: true);
        AnalysisSynthesisItem::create($this->synthesisPayload($survey));

        $this->actingAs($admin)
            ->get(route('admin.surveys.collection-monitoring.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Submitted responses: 2')
            ->assertSeeText('Submitted responses: 1')
            ->assertSeeText('Submitted validators: 1')
            ->assertSeeText('Submitted participants: 1')
            ->assertSeeText('Accepted items: 1');
    }

    public function test_status_changes_from_not_started_to_collecting_minimum_target_and_overdue(): void
    {
        [$admin, $survey] = $this->surveyFixture();

        $this->actingAs($admin)
            ->get(route('admin.surveys.collection-monitoring.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Not Started');

        $this->submitResponses($survey, 1);
        $this->actingAs($admin)
            ->get(route('admin.surveys.collection-monitoring.index', ['survey' => $survey->fresh()]))
            ->assertOk()
            ->assertSeeText('Collecting');

        $target = AnalysisCollectionTarget::where('survey_id', $survey->id)
            ->where('source_type', AnalysisCollectionTarget::SOURCE_STUDENT_QUESTIONNAIRE)
            ->firstOrFail();
        $target->update(['minimum_count' => 1, 'target_count' => 3]);

        $this->actingAs($admin)
            ->get(route('admin.surveys.collection-monitoring.index', ['survey' => $survey->fresh()]))
            ->assertOk()
            ->assertSeeText('Minimum Met');

        $this->submitResponses($survey, 2);
        $this->actingAs($admin)
            ->get(route('admin.surveys.collection-monitoring.index', ['survey' => $survey->fresh()]))
            ->assertOk()
            ->assertSeeText('Target Met');

        $target->update(['minimum_count' => 5, 'target_count' => 10, 'due_date' => today()->subDay()]);
        $this->actingAs($admin)
            ->get(route('admin.surveys.collection-monitoring.index', ['survey' => $survey->fresh()]))
            ->assertOk()
            ->assertSeeText('Overdue');
    }

    public function test_overall_readiness_panel_reports_minimum_ready_when_all_minimums_are_met(): void
    {
        [$admin, $survey] = $this->surveyFixture();
        $lecturer = app(CreateLecturerNeedsAnalysisQuestionnaireAction::class)->handle($admin, $survey);
        $practitioner = app(CreatePractitionerInterviewFormAction::class)->handle($admin, $survey->fresh());
        $this->actingAs($admin)->get(route('admin.surveys.collection-monitoring.index', ['survey' => $survey]));

        AnalysisCollectionTarget::where('survey_id', $survey->id)->update(['minimum_count' => 1, 'target_count' => 2]);
        $this->submitResponses($survey, 1);
        $this->submitResponses($lecturer, 1);
        $this->submitResponses($practitioner, 1);
        $this->validationFixture($admin, $survey, submitted: true);
        $this->readabilityFixture($admin, $survey, submitted: true);
        AnalysisSynthesisItem::create($this->synthesisPayload($survey));

        $this->actingAs($admin)
            ->get(route('admin.surveys.collection-monitoring.index', ['survey' => $survey->fresh()]))
            ->assertOk()
            ->assertSeeText('Minimum Ready')
            ->assertSeeText('Data minimal telah terpenuhi');
    }

    public function test_printable_report_export_and_existing_pages_load(): void
    {
        [$admin, $survey] = $this->surveyFixture();

        $this->actingAs($admin)
            ->get(route('admin.surveys.collection-monitoring.report', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Analysis Collection Monitoring Report')
            ->assertSeeText('Data Source Status');

        $this->actingAs($admin)
            ->get(route('admin.surveys.collection-monitoring.export', ['survey' => $survey]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($admin)
            ->get(route('admin.surveys.distribution.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Research Distribution Center')
            ->assertSeeText('Collection Monitoring');

        $this->actingAs($admin)
            ->get(route('admin.surveys.analysis.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('ADDIE Analysis Dashboard')
            ->assertSeeText('Collection Monitoring');
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

        SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'student_need',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Mahasiswa membutuhkan PharmVR untuk memahami CPOB.',
            'options' => ['scale' => [1, 2, 3, 4, 5]],
            'is_required' => true,
            'sort_order' => 1,
        ]);

        return [$admin, $survey->fresh()];
    }

    private function submitResponses(Survey $survey, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            SurveyResponse::create([
                'survey_id' => $survey->id,
                'status' => SurveyResponse::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'metadata' => ['identity_mode' => $survey->identity_mode],
            ]);
        }
    }

    private function validationFixture(User $admin, Survey $survey, bool $submitted = false): SurveyValidationAssignment
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
            'email' => 'validator@example.test',
            'is_active' => true,
        ]);

        $assignment = SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->id,
            'expert_validator_id' => $validator->id,
            'role' => 'content_expert',
            'status' => SurveyValidationAssignment::STATUS_PENDING,
            'created_by' => $admin->id,
        ]);

        if ($submitted) {
            $assignment->markSubmitted();
        }

        return $assignment->fresh();
    }

    private function readabilityFixture(User $admin, Survey $survey, bool $submitted = false): SurveyReadabilityParticipant
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

        if ($submitted) {
            $participant->markSubmitted();
        }

        return $participant->fresh();
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
