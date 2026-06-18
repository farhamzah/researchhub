<?php

namespace Tests\Feature;

use App\Models\AnalysisCollectionTarget;
use App\Models\AnalysisPreflightReview;
use App\Models\AnalysisSynthesisItem;
use App\Models\ExpertValidator;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyDistributionBatch;
use App\Models\SurveyQuestion;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyReadabilityResponse;
use App\Models\SurveyReadabilityRound;
use App\Models\SurveyResponse;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRecommendation;
use App\Models\SurveyValidationRound;
use App\Models\User;
use App\Modules\Analysis\Services\AnalysisPreflightQaService;
use App\Modules\Surveys\Actions\CreateLecturerNeedsAnalysisQuestionnaireAction;
use App\Modules\Surveys\Actions\CreatePractitionerInterviewFormAction;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use App\Modules\Surveys\Services\PharmVrStudentNeedsSurveyTemplateService;
use App\Modules\Surveys\Support\SurveyIntroTemplates;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisPreflightQaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_preflight_page_and_see_missing_items_without_sensitive_tokens(): void
    {
        [$admin, $survey] = $this->surveyFixture();

        $this->actingAs($admin)
            ->get(route('admin.surveys.preflight.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Pre-Distribution QA Checklist')
            ->assertSeeText('Not Ready')
            ->assertSeeText('Student Questionnaire Readiness')
            ->assertSeeText('Approved 43 student questionnaire keys')
            ->assertSeeText('Official Section H open-ended feedback')
            ->assertSeeText('Lecturer Questionnaire is missing.')
            ->assertSeeText('Practitioner Interview Form is missing.')
            ->assertDontSeeText('Add Missing Section G Questions')
            ->assertDontSeeText('Official Section G open-response items')
            ->assertDontSee('token_hash')
            ->assertDontSee('response_token_hash');
    }

    public function test_preflight_routes_are_authenticated_and_project_authorized(): void
    {
        [$admin, $survey] = $this->surveyFixture();
        $other = User::factory()->create();
        $other->assignRole('admin');

        $this->get(route('admin.surveys.preflight.index', ['survey' => $survey]))
            ->assertRedirect('/admin/login');

        $this->actingAs($other)
            ->get(route('admin.surveys.preflight.index', ['survey' => $survey]))
            ->assertForbidden();
    }

    public function test_student_questionnaire_scope_uses_final_43_keys_and_does_not_add_obsolete_section_g(): void
    {
        [$admin, $survey] = $this->surveyFixture();

        $this->actingAs($admin)
            ->post(route('admin.surveys.preflight.fix-student-open-questions', ['survey' => $survey]))
            ->assertRedirect(route('admin.surveys.preflight.index', [
                'survey' => $survey,
                'scope' => AnalysisPreflightQaService::SCOPE_STUDENT_QUESTIONNAIRE,
            ]));

        foreach (AnalysisPreflightQaService::OBSOLETE_STUDENT_KEYS as $key) {
            $this->assertDatabaseMissing('survey_questions', [
                'survey_id' => $survey->id,
                'question_key' => $key,
            ]);
        }

        $survey->questions()->delete();
        app(PharmVrStudentNeedsSurveyTemplateService::class)->fillMissing($admin, $survey->fresh());
        $survey = $survey->fresh(['questions.scoring']);

        foreach (AnalysisPreflightQaService::APPROVED_STUDENT_KEYS as $key) {
            $this->assertDatabaseHas('survey_questions', [
                'survey_id' => $survey->id,
                'question_key' => $key,
            ]);
        }

        $this->assertSame(43, $survey->questions()->whereIn('question_key', AnalysisPreflightQaService::APPROVED_STUDENT_KEYS)->count());
        $this->assertSame(5, $survey->questions()->whereIn('question_key', AnalysisPreflightQaService::OFFICIAL_STUDENT_OPEN_KEYS)->where('type', SurveyQuestion::TYPE_LONG_TEXT)->count());
        $this->assertSame(0, $survey->questions()->whereIn('question_key', AnalysisPreflightQaService::OBSOLETE_STUDENT_KEYS)->count());

        $qa = app(AnalysisPreflightQaService::class)->build(
            $survey,
            $admin,
            AnalysisPreflightQaService::SCOPE_STUDENT_QUESTIONNAIRE,
        );

        $this->assertSame(43, $qa['student_readiness']['approved_present']);
        $this->assertSame(43, $qa['student_readiness']['approved_total']);
        $this->assertSame([], $qa['student_readiness']['obsolete_keys']);
        $this->assertSame(0, $qa['student_readiness']['missing_scoring']);
        $this->assertTrue($qa['student_readiness']['consent_valid']);
        $this->assertTrue($qa['student_readiness']['g_priority_valid']);
        $this->assertTrue($qa['student_readiness']['f6_risk_descriptive']);
    }

    public function test_preflight_scope_demotes_later_workflow_items_for_student_questionnaire(): void
    {
        [$admin, $survey] = $this->approvedStudentFixture();

        $qa = app(AnalysisPreflightQaService::class)->build(
            $survey,
            $admin,
            AnalysisPreflightQaService::SCOPE_STUDENT_QUESTIONNAIRE,
        );

        $this->assertSame(0, $qa['summary']['critical_failed']);
        $this->assertSame('Needs Attention', $qa['summary']['overall_status']);
        $this->assertSame('enabled', $qa['student_readiness']['public_access']);
        $this->assertSame('pending', $qa['student_readiness']['readability']);
        $this->assertSame('pending', $qa['student_readiness']['expert_validation']);

        $checks = collect($qa['checks'])->keyBy('check_key');
        $this->assertSame('info', $checks->get('lecturer_questionnaire.exists')['severity']);
        $this->assertSame('warning', $checks->get('validation.round')['severity']);
        $this->assertSame('warning', $checks->get('readability.round')['severity']);
    }

    public function test_public_access_is_warning_for_student_scope_and_critical_for_distribution_scope(): void
    {
        [$admin, $survey] = $this->approvedStudentFixture();
        $survey->forceFill([
            'status' => Survey::STATUS_DRAFT,
            'is_public' => false,
            'published_at' => null,
        ])->save();

        $studentQa = app(AnalysisPreflightQaService::class)->build(
            $survey->fresh(),
            $admin,
            AnalysisPreflightQaService::SCOPE_STUDENT_QUESTIONNAIRE,
        );
        $distributionQa = app(AnalysisPreflightQaService::class)->build(
            $survey->fresh(),
            $admin,
            AnalysisPreflightQaService::SCOPE_DISTRIBUTION,
        );

        $studentPublicAccess = collect($studentQa['checks'])->firstWhere('check_key', 'student_questionnaire.public_access');
        $distributionPublicAccess = collect($distributionQa['checks'])->firstWhere('check_key', 'student_questionnaire.public_access');

        $this->assertSame('warning', $studentPublicAccess['severity']);
        $this->assertSame('warning', $studentPublicAccess['status']);
        $this->assertSame('critical', $distributionPublicAccess['severity']);
        $this->assertSame('failed', $distributionPublicAccess['status']);
    }

    public function test_obsolete_g3_to_g5_keys_are_reported_as_extra_keys(): void
    {
        [$admin, $survey] = $this->approvedStudentFixture();
        $page = $survey->pages()->firstOrFail();

        foreach (AnalysisPreflightQaService::OBSOLETE_STUDENT_KEYS as $index => $key) {
            $survey->questions()->create([
                'page_id' => $page->id,
                'question_key' => $key,
                'type' => SurveyQuestion::TYPE_LONG_TEXT,
                'label' => 'Obsolete open response '.$key,
                'is_required' => false,
                'sort_order' => 100 + $index,
            ]);
        }

        $qa = app(AnalysisPreflightQaService::class)->build(
            $survey->fresh(['questions']),
            $admin,
            AnalysisPreflightQaService::SCOPE_STUDENT_QUESTIONNAIRE,
        );

        $this->assertSame(AnalysisPreflightQaService::OBSOLETE_STUDENT_KEYS, $qa['student_readiness']['obsolete_keys']);
        $check = collect($qa['checks'])->firstWhere('check_key', 'student.no_obsolete_g_open_questions');
        $this->assertSame('warning', $check['severity']);
        $this->assertSame('warning', $check['status']);
        $this->assertSame('Remove Obsolete Keys', $check['fix_action_label']);
        $this->assertStringContainsString('Obsolete extra keys found: G3, G4, G5', $check['message']);

        $this->actingAs($admin)
            ->post(route('admin.surveys.preflight.remove-obsolete-student-keys', ['survey' => $survey]))
            ->assertRedirect(route('admin.surveys.preflight.index', [
                'survey' => $survey,
                'scope' => AnalysisPreflightQaService::SCOPE_STUDENT_QUESTIONNAIRE,
            ]));

        $this->assertSame(0, $survey->fresh()->questions()->whereIn('question_key', AnalysisPreflightQaService::OBSOLETE_STUDENT_KEYS)->count());
    }

    public function test_full_analysis_package_scope_still_reports_global_readiness(): void
    {
        [$admin, $survey] = $this->approvedStudentFixture();

        $qa = app(AnalysisPreflightQaService::class)->build(
            $survey,
            $admin,
            AnalysisPreflightQaService::SCOPE_FULL_ANALYSIS_PACKAGE,
        );

        $checks = collect($qa['checks'])->keyBy('check_key');

        $this->assertGreaterThan(0, $qa['summary']['critical_failed']);
        $this->assertSame('critical', $checks->get('lecturer_questionnaire.exists')['severity']);
        $this->assertSame('failed', $checks->get('lecturer_questionnaire.exists')['status']);
        $this->assertSame('critical', $checks->get('validation.round')['severity']);
    }

    public function test_preflight_report_and_csv_export_work(): void
    {
        [$admin, $survey] = $this->surveyFixture();

        $this->actingAs($admin)
            ->get(route('admin.surveys.preflight.report', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Pre-Distribution QA Report')
            ->assertSeeText('Executive Summary');

        $this->actingAs($admin)
            ->get(route('admin.surveys.preflight.export', ['survey' => $survey]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_mark_ready_is_blocked_when_critical_failures_remain(): void
    {
        [$admin, $survey] = $this->surveyFixture();

        $this->actingAs($admin)
            ->post(route('admin.surveys.preflight.mark-ready', ['survey' => $survey]), [
                'notes' => 'Attempt before resolving critical issues.',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('analysis_preflight_reviews', [
            'survey_id' => $survey->id,
            'status' => AnalysisPreflightReview::STATUS_READY,
        ]);
    }

    public function test_mark_ready_records_snapshot_when_no_critical_failures_remain(): void
    {
        [$admin, $survey] = $this->readyFixture();

        $this->actingAs($admin)
            ->post(route('admin.surveys.preflight.mark-ready', ['survey' => $survey]), [
                'notes' => 'Ready for controlled distribution.',
            ])
            ->assertRedirect(route('admin.surveys.preflight.index', [
                'survey' => $survey,
                'scope' => AnalysisPreflightQaService::SCOPE_STUDENT_QUESTIONNAIRE,
            ]));

        $review = AnalysisPreflightReview::where('survey_id', $survey->id)->firstOrFail();

        $this->assertSame(AnalysisPreflightReview::STATUS_READY, $review->status);
        $this->assertSame($admin->id, $review->reviewed_by);
        $this->assertSame('Ready for controlled distribution.', $review->notes);
        $this->assertIsArray($review->snapshot_json);
        $this->assertContains($review->snapshot_json['overall_status'], ['Ready to Send', 'Ready with Notes', 'Needs Attention']);
    }

    public function test_existing_analysis_pages_include_preflight_link(): void
    {
        [$admin, $survey] = $this->surveyFixture();

        $this->actingAs($admin)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Preflight QA');

        $this->actingAs($admin)
            ->get(route('admin.surveys.analysis.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Preflight QA');

        $this->actingAs($admin)
            ->get(route('admin.surveys.distribution.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Preflight QA');

        $this->actingAs($admin)
            ->get(route('admin.surveys.collection-monitoring.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Preflight QA');
    }

    /**
     * @return array{0: User, 1: Survey}
     */
    private function readyFixture(): array
    {
        [$admin, $survey] = $this->approvedStudentFixture();

        $lecturer = app(CreateLecturerNeedsAnalysisQuestionnaireAction::class)->handle($admin, $survey);
        $practitioner = app(CreatePractitionerInterviewFormAction::class)->handle($admin, $survey->fresh());

        foreach ([$survey->fresh(), $lecturer->fresh(), $practitioner->fresh()] as $instrument) {
            if ($instrument->status !== Survey::STATUS_PUBLISHED) {
                app(PublishSurveyAction::class)->handle($admin, $instrument);
            }
        }

        $this->actingAs($admin)->get(route('admin.surveys.preflight.index', ['survey' => $survey]));
        AnalysisCollectionTarget::where('survey_id', $survey->id)
            ->update([
                'minimum_count' => 1,
                'target_count' => 1,
                'due_date' => today()->addDays(14),
            ]);

        $this->submitResponse($survey);
        $this->submitResponse($lecturer);
        $this->submitResponse($practitioner);
        $this->submittedValidationFixture($admin, $survey);
        $this->submittedReadabilityFixture($admin, $survey);
        $this->distributionFixture($admin, $survey);
        AnalysisSynthesisItem::create($this->synthesisPayload($survey));

        return [$admin, $survey->fresh()];
    }

    /**
     * @return array{0: User, 1: Survey}
     */
    private function approvedStudentFixture(): array
    {
        [$admin, $survey] = $this->surveyFixture();

        $survey->questions()->delete();
        app(PharmVrStudentNeedsSurveyTemplateService::class)->fillMissing($admin, $survey->fresh());
        app(PublishSurveyAction::class)->handle($admin, $survey->fresh());

        return [$admin, $survey->fresh()];
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

        SurveyValidationRecommendation::create([
            'survey_validation_assignment_id' => $assignment->id,
            'survey_id' => $survey->id,
            'overall_score' => 4.4,
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
            'target_participants' => 1,
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
        foreach (SurveyDistributionBatch::AUDIENCES as $audience) {
            SurveyDistributionBatch::create([
                'survey_id' => $survey->id,
                'project_id' => $survey->project_id,
                'audience_type' => $audience,
                'title' => 'Distribution '.$audience,
                'message_subject' => 'Undangan Partisipasi',
                'message_body' => 'Silakan mengisi instrumen melalui link yang disediakan.',
                'deadline' => today()->addDays(14),
                'status' => SurveyDistributionBatch::STATUS_READY,
                'created_by' => $admin->id,
            ]);
        }
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
