<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Models\User;
use App\Modules\AcademicOutputs\Services\AcademicNarrativeService;
use App\Modules\Analysis\Services\AnalysisPreflightQaService;
use App\Modules\Surveys\Actions\CreateLecturerNeedsAnalysisQuestionnaireAction;
use App\Modules\Surveys\Actions\CreatePractitionerInterviewFormAction;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use App\Modules\Surveys\Services\SurveyBuilderReadinessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisInstrumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecturer_questionnaire_template_has_expected_keys_scales_limits_and_scoring(): void
    {
        [$owner, $student] = $this->mainSurveyFixture();
        $lecturer = app(CreateLecturerNeedsAnalysisQuestionnaireAction::class)->handle($owner, $student);

        $this->assertSame($student->id, $lecturer->parent_survey_id);
        $this->assertSame(Survey::INSTRUMENT_ANALYSIS_LECTURER, $lecturer->instrument_type);
        $this->assertSame(8, $lecturer->pages()->count());
        $this->assertSame(40, $lecturer->questions()->count());

        foreach (AnalysisPreflightQaService::APPROVED_LECTURER_KEYS as $key) {
            $this->assertDatabaseHas('survey_questions', ['survey_id' => $lecturer->id, 'question_key' => $key]);
        }

        $likert = $lecturer->questions()->where('question_key', 'L_C1')->firstOrFail();
        $this->assertSame(SurveyQuestion::TYPE_LIKERT, $likert->type);
        $this->assertSame([1, 2, 3, 4, 5], $likert->settings['scale']);
        $this->assertTrue($likert->scoring()->firstOrFail()->is_scored);

        $this->assertSame(3, data_get($lecturer->questions()->where('question_key', 'L_G1')->firstOrFail()->settings, 'max_selections'));
        $this->assertSame(3, data_get($lecturer->questions()->where('question_key', 'L_G2')->firstOrFail()->settings, 'max_selections'));

        $risk = $lecturer->questions()->where('question_key', 'L_F6')->firstOrFail()->scoring()->firstOrFail();
        $this->assertFalse($risk->is_scored);
        $this->assertTrue((bool) data_get($risk->settings, 'risk_item'));
        $this->assertTrue((bool) data_get($risk->settings, 'not_positive_readiness'));

        $open = $lecturer->questions()->where('question_key', 'L_H1')->firstOrFail();
        $this->assertFalse($open->scoring()->firstOrFail()->is_scored);
        $this->assertSame('Masukan Terbuka', $open->scoring()->firstOrFail()->indicator->name);
        $this->assertSame(0, app(SurveyBuilderReadinessService::class)->build($lecturer)['scoring']['missing']);
    }

    public function test_practitioner_interview_template_has_expected_keys_limits_and_descriptive_items(): void
    {
        [$owner, $student] = $this->mainSurveyFixture();
        $form = app(CreatePractitionerInterviewFormAction::class)->handle($owner, $student);

        $this->assertSame($student->id, $form->parent_survey_id);
        $this->assertSame(Survey::INSTRUMENT_PRACTITIONER_INTERVIEW, $form->instrument_type);
        $this->assertSame(6, $form->pages()->count());
        $this->assertSame(26, $form->questions()->count());

        foreach (AnalysisPreflightQaService::APPROVED_PRACTITIONER_KEYS as $key) {
            $this->assertDatabaseHas('survey_questions', ['survey_id' => $form->id, 'question_key' => $key]);
        }

        $this->assertSame(5, data_get($form->questions()->where('question_key', 'P_C1')->firstOrFail()->settings, 'max_selections'));
        $this->assertSame(5, data_get($form->questions()->where('question_key', 'P_E1')->firstOrFail()->settings, 'max_selections'));

        $longText = $form->questions()->where('question_key', 'P_B1')->firstOrFail();
        $this->assertSame(SurveyQuestion::TYPE_LONG_TEXT, $longText->type);
        $this->assertFalse($longText->scoring()->firstOrFail()->is_scored);
        $this->assertSame('Kebutuhan Konten CPOB/GMP', $longText->scoring()->firstOrFail()->indicator->name);

        $priorityScoring = $form->questions()->where('question_key', 'P_C1')->firstOrFail()->scoring()->firstOrFail();
        $this->assertFalse($priorityScoring->is_scored);
        $this->assertTrue((bool) data_get($priorityScoring->settings, 'descriptive'));
        $this->assertSame([
            'Profil Narasumber dan Keahlian',
            'Fokus Wawancara dan Tema Utama',
            'Kebutuhan Konten CPOB/GMP',
            'Validasi Alur Produksi dan Scene',
            'Risiko Miskonsepsi dan Ketidakakuratan',
            'Prioritas Scene dan Fitur',
            'Implementasi, Kelayakan, dan Rekomendasi Industri',
        ], $form->indicators()->orderBy('sort_order')->pluck('name')->all());
        $this->assertSame(0, app(SurveyBuilderReadinessService::class)->build($form)['scoring']['missing']);
    }

    public function test_duplicate_generation_prevents_duplicate_surveys_and_fill_missing_only_adds_missing_keys(): void
    {
        [$owner, $student] = $this->mainSurveyFixture();
        $lecturer = app(CreateLecturerNeedsAnalysisQuestionnaireAction::class)->handle($owner, $student);
        $originalStudentUpdatedAt = $student->fresh()->updated_at;

        $lecturer->questions()->where('question_key', 'L_H5')->delete();
        $this->assertDatabaseMissing('survey_questions', ['survey_id' => $lecturer->id, 'question_key' => 'L_H5']);

        app(CreateLecturerNeedsAnalysisQuestionnaireAction::class)->handle($owner, $student);

        $this->assertSame(1, Survey::query()->where('instrument_type', Survey::INSTRUMENT_ANALYSIS_LECTURER)->count());
        $this->assertSame(40, $lecturer->fresh()->questions()->count());
        $this->assertDatabaseHas('survey_questions', ['survey_id' => $lecturer->id, 'question_key' => 'L_H5']);
        $this->assertEquals($originalStudentUpdatedAt, $student->fresh()->updated_at);
    }

    public function test_respondent_package_preflight_supervisor_review_public_and_pilot_integrations_work(): void
    {
        [$owner, $student] = $this->mainSurveyFixture();
        $lecturer = app(CreateLecturerNeedsAnalysisQuestionnaireAction::class)->handle($owner, $student);
        $practitioner = app(CreatePractitionerInterviewFormAction::class)->handle($owner, $student);

        $this->actingAs($owner)
            ->get(route('admin.surveys.respondent-package.index', ['survey' => $student]))
            ->assertOk()
            ->assertSeeText('Student Questionnaire')
            ->assertSeeText('Lecturer Questionnaire')
            ->assertSeeText('Practitioner Interview Form');

        $lecturerQa = app(AnalysisPreflightQaService::class)->build($student->fresh(), $owner, 'lecturer_questionnaire');
        $this->assertSame('passed', collect($lecturerQa['checks'])->firstWhere('check_key', 'lecturer.approved_keys')['status']);
        $this->assertSame('passed', collect($lecturerQa['checks'])->firstWhere('check_key', 'lecturer.priority_max_three')['status']);

        $practitionerQa = app(AnalysisPreflightQaService::class)->build($student->fresh(), $owner, 'practitioner_interview');
        $this->assertSame('passed', collect($practitionerQa['checks'])->firstWhere('check_key', 'practitioner.approved_keys')['status']);
        $this->assertSame('passed', collect($practitionerQa['checks'])->firstWhere('check_key', 'practitioner.priority_max_five')['status']);

        $this->actingAs($owner)
            ->get(route('admin.surveys.supervisor-review.index', ['survey' => $lecturer]))
            ->assertOk()
            ->assertSeeText('Supervisor Instrument Review')
            ->assertSeeText('Kuesioner Analisis Kebutuhan Dosen terhadap Media Pembelajaran Virtual Reality');

        app(PublishSurveyAction::class)->handle($owner, $lecturer);
        $this->get(route('survey.show', ['survey' => $lecturer->fresh()->slug]))
            ->assertOk()
            ->assertSeeText('Pengantar Kuesioner Analisis Kebutuhan Dosen PharmVR');

        $lecturer->forceFill(['status' => Survey::STATUS_DRAFT, 'is_public' => false, 'published_at' => null])->save();
        $this->actingAs($owner)
            ->post(route('admin.surveys.respondent-package.pilot.generate', ['survey' => $student, 'audience' => 'lecturer']))
            ->assertRedirect(route('admin.surveys.respondent-package.index', ['survey' => $student]))
            ->assertSessionHas('generated_pilot_url');

        $this->get(session('generated_pilot_url'))
            ->assertOk()
            ->assertSeeText('PILOT/REVIEWER MODE')
            ->assertSeeText('Pengantar Kuesioner Analisis Kebutuhan Dosen PharmVR');

        $this->assertSame(Survey::INSTRUMENT_PRACTITIONER_INTERVIEW, $practitioner->instrument_type);
    }

    public function test_practitioner_builder_is_scope_aware_and_uses_qualitative_summary(): void
    {
        [$owner, $student] = $this->mainSurveyFixture();
        $practitioner = app(CreatePractitionerInterviewFormAction::class)->handle($owner, $student);

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $practitioner]))
            ->assertOk()
            ->assertDontSeeText('Create PharmVR Student Needs Survey')
            ->assertDontSeeText('Fill Missing Student Sections')
            ->assertDontSeeText('Normalize Student Survey Wording')
            ->assertDontSeeText('PharmVR student template keys')
            ->assertSeeText('Normalize Practitioner Interview Form')
            ->assertSeeText('This interview form is qualitative; descriptive indicators are used for thematic analysis')
            ->assertSeeText('Pedoman Wawancara Praktisi/Ahli CPOB PharmVR merupakan instrumen kualitatif terstruktur');

        $summary = app(AcademicNarrativeService::class)->surveyInstrumentSummary($practitioner);
        $this->assertStringContainsString('tidak dirancang sebagai instrumen skor numerik', $summary);
    }

    public function test_practitioner_preflight_uses_descriptive_readiness_without_numeric_scoring(): void
    {
        [$owner, $student] = $this->mainSurveyFixture();
        app(CreatePractitionerInterviewFormAction::class)->handle($owner, $student);

        $qa = app(AnalysisPreflightQaService::class)->build($student->fresh(), $owner, AnalysisPreflightQaService::SCOPE_PRACTITIONER_INTERVIEW);
        $checks = collect($qa['checks'])->keyBy('check_key');

        $this->assertSame('passed', $checks->get('practitioner.descriptive_indicators')['status']);
        $this->assertSame('passed', $checks->get('practitioner.long_text_descriptive')['status']);
        $this->assertSame('skipped', $checks->get('student.final_43_keys')['status']);
    }

    public function test_full_analysis_package_detects_missing_instruments(): void
    {
        [$owner, $student] = $this->mainSurveyFixture();

        $qa = app(AnalysisPreflightQaService::class)->build($student, $owner, AnalysisPreflightQaService::SCOPE_FULL_ANALYSIS_PACKAGE);

        $this->assertSame('failed', collect($qa['checks'])->firstWhere('check_key', 'lecturer_questionnaire.exists')['status']);
        $this->assertSame('failed', collect($qa['checks'])->firstWhere('check_key', 'practitioner_interview.exists')['status']);
    }

    /**
     * @return array{0: User, 1: Survey}
     */
    private function mainSurveyFixture(): array
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
            'title' => 'Kuesioner Analisis Kebutuhan Mahasiswa PharmVR',
            'description' => 'Student needs analysis questionnaire for PharmVR Analysis ADDIE stage.',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
            'instrument_type' => Survey::INSTRUMENT_ANALYSIS_STUDENT,
            'analysis_group_key' => Survey::ANALYSIS_GROUP_PHARMVR_ADDIE,
        ]);

        SurveyPage::create([
            'survey_id' => $survey->id,
            'title' => 'Student Section',
            'sort_order' => 1,
        ]);

        SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'student_need',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Mahasiswa membutuhkan PharmVR untuk memahami CPOB.',
            'options' => ['scale' => [1, 2, 3, 4, 5]],
            'settings' => ['scale' => [1, 2, 3, 4, 5]],
            'is_required' => true,
            'sort_order' => 1,
        ]);

        return [$owner, $survey->fresh()];
    }
}
