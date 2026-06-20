<?php

namespace Tests\Feature;

use App\Models\AnalysisSynthesisItem;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LecturerPractitionerAnalysisInstrumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_lecturer_questionnaire_and_duplicates_are_prevented(): void
    {
        [$owner, $mainSurvey] = $this->mainSurveyFixture();

        $this->actingAs($owner)
            ->post(route('admin.surveys.analysis.create-lecturer-questionnaire', ['survey' => $mainSurvey]))
            ->assertRedirect();

        $lecturerSurvey = Survey::query()
            ->where('instrument_type', Survey::INSTRUMENT_ANALYSIS_LECTURER)
            ->firstOrFail();

        $this->assertSame($mainSurvey->id, $lecturerSurvey->parent_survey_id);
        $this->assertSame(Survey::ANALYSIS_GROUP_PHARMVR_ADDIE, $lecturerSurvey->analysis_group_key);
        $this->assertSame('Pengantar Kuesioner Analisis Kebutuhan Dosen PharmVR', $lecturerSurvey->intro_title);
        $this->assertSame('10-15 menit', $lecturerSurvey->estimated_duration);
        $this->assertTrue($lecturerSurvey->require_consent_before_start);
        $this->assertSame(8, $lecturerSurvey->pages()->count());
        $this->assertDatabaseHas('survey_questions', [
            'survey_id' => $lecturerSurvey->id,
            'question_key' => 'L_C1',
            'label' => 'Saya telah mengajarkan atau mendampingi pembelajaran yang berkaitan dengan CPOB/GMP atau farmasi industri.',
        ]);
        $this->assertDatabaseHas('survey_questions', [
            'survey_id' => $lecturerSurvey->id,
            'question_key' => 'L_G1',
            'type' => SurveyQuestion::TYPE_MULTIPLE_CHOICE,
        ]);

        $this->actingAs($owner)
            ->post(route('admin.surveys.analysis.create-lecturer-questionnaire', ['survey' => $mainSurvey]))
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $lecturerSurvey]));

        $this->assertSame(1, Survey::query()->where('instrument_type', Survey::INSTRUMENT_ANALYSIS_LECTURER)->count());
    }

    public function test_admin_can_generate_practitioner_interview_form_and_duplicates_are_prevented(): void
    {
        [$owner, $mainSurvey] = $this->mainSurveyFixture();

        $this->actingAs($owner)
            ->post(route('admin.surveys.analysis.create-practitioner-interview', ['survey' => $mainSurvey]))
            ->assertRedirect();

        $form = Survey::query()
            ->where('instrument_type', Survey::INSTRUMENT_PRACTITIONER_INTERVIEW)
            ->firstOrFail();

        $this->assertSame($mainSurvey->id, $form->parent_survey_id);
        $this->assertSame('Pengantar Wawancara Praktisi/Ahli CPOB PharmVR', $form->intro_title);
        $this->assertStringContainsString('disamarkan', $form->privacy_statement);
        $this->assertTrue($form->require_consent_before_start);
        $this->assertSame(6, $form->pages()->count());
        $this->assertDatabaseHas('survey_questions', [
            'survey_id' => $form->id,
            'question_key' => 'P_C1',
            'type' => SurveyQuestion::TYPE_MULTIPLE_CHOICE,
        ]);
        $this->assertDatabaseHas('survey_questions', [
            'survey_id' => $form->id,
            'question_key' => 'P_D1',
            'label' => 'Bagian mana dari simulasi farmasi industri yang paling berisiko menimbulkan miskonsepsi jika divisualisasikan secara tidak tepat?',
        ]);

        $this->actingAs($owner)
            ->post(route('admin.surveys.analysis.create-practitioner-interview', ['survey' => $mainSurvey]))
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $form]));

        $this->assertSame(1, Survey::query()->where('instrument_type', Survey::INSTRUMENT_PRACTITIONER_INTERVIEW)->count());
    }

    public function test_analysis_dashboard_shows_related_instruments_and_open_links(): void
    {
        [$owner, $mainSurvey] = $this->mainSurveyFixture();

        $this->actingAs($owner)
            ->get(route('admin.surveys.analysis.index', ['survey' => $mainSurvey]))
            ->assertOk()
            ->assertSeeText('Create Lecturer Questionnaire')
            ->assertSeeText('Create Practitioner Interview Form');

        $this->actingAs($owner)
            ->post(route('admin.surveys.analysis.create-lecturer-questionnaire', ['survey' => $mainSurvey]));
        $this->actingAs($owner)
            ->post(route('admin.surveys.analysis.create-practitioner-interview', ['survey' => $mainSurvey]));

        $this->actingAs($owner)
            ->get(route('admin.surveys.analysis.index', ['survey' => $mainSurvey->fresh()]))
            ->assertOk()
            ->assertSeeText('Lecturer Questionnaire')
            ->assertSeeText('Practitioner Interview Form')
            ->assertSeeText('Open Builder')
            ->assertSeeText('Response Summary');
    }

    public function test_generated_instruments_can_be_opened_in_builder_and_public_form(): void
    {
        [$owner, $mainSurvey] = $this->mainSurveyFixture();
        $this->actingAs($owner)->post(route('admin.surveys.analysis.create-lecturer-questionnaire', ['survey' => $mainSurvey]));
        $this->actingAs($owner)->post(route('admin.surveys.analysis.create-practitioner-interview', ['survey' => $mainSurvey]));

        $lecturerSurvey = Survey::query()->where('instrument_type', Survey::INSTRUMENT_ANALYSIS_LECTURER)->firstOrFail();
        $practitionerSurvey = Survey::query()->where('instrument_type', Survey::INSTRUMENT_PRACTITIONER_INTERVIEW)->firstOrFail();
        app(PublishSurveyAction::class)->handle($owner, $lecturerSurvey);
        app(PublishSurveyAction::class)->handle($owner, $practitionerSurvey);

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $lecturerSurvey]))
            ->assertOk()
            ->assertSeeText('Kuesioner Analisis Kebutuhan Dosen terhadap Media Pembelajaran Virtual Reality');

        $this->get(route('survey.show', ['survey' => $lecturerSurvey->fresh()->slug]))
            ->assertOk()
            ->assertSeeText('Kuesioner Analisis Kebutuhan Dosen terhadap Media Pembelajaran Virtual Reality')
            ->assertSeeText('Pengantar Kuesioner Analisis Kebutuhan Dosen PharmVR')
            ->assertSeeText('Saya telah membaca penjelasan di atas dan bersedia melanjutkan.');

        $this->get(route('survey.show', ['survey' => $practitionerSurvey->fresh()->slug]))
            ->assertOk()
            ->assertSeeText('Pedoman Wawancara Praktisi/Ahli CPOB untuk Analisis Kebutuhan PharmVR')
            ->assertSeeText('Pengantar Wawancara Praktisi/Ahli CPOB PharmVR');
    }

    public function test_generate_draft_synthesis_includes_lecturer_and_practitioner_data(): void
    {
        [$owner, $mainSurvey] = $this->mainSurveyFixture();
        $this->actingAs($owner)->post(route('admin.surveys.analysis.create-lecturer-questionnaire', ['survey' => $mainSurvey]));
        $this->actingAs($owner)->post(route('admin.surveys.analysis.create-practitioner-interview', ['survey' => $mainSurvey]));

        $lecturerSurvey = Survey::query()->where('instrument_type', Survey::INSTRUMENT_ANALYSIS_LECTURER)->firstOrFail();
        $practitionerSurvey = Survey::query()->where('instrument_type', Survey::INSTRUMENT_PRACTITIONER_INTERVIEW)->firstOrFail();

        $this->response($lecturerSurvey, [
            'L_E2' => 5,
            'L_D4' => 5,
            'L_G1' => ['Hygiene dan gowning', 'Weighing'],
        ]);
        $this->response($practitionerSurvey, [
            'P_C1' => ['Hygiene dan gowning', 'Weighing'],
            'P_D1' => 'Risiko utama adalah mahasiswa salah memahami alur dokumentasi dan status label.',
            'P_C3' => 'Weighing harus menampilkan status label, logbook, dan verifikasi material.',
        ]);

        $this->actingAs($owner)
            ->post(route('admin.surveys.analysis.generate-synthesis', ['survey' => $mainSurvey]))
            ->assertRedirect(route('admin.surveys.analysis.index', ['survey' => $mainSurvey]));

        $this->assertDatabaseHas('analysis_synthesis_items', [
            'survey_id' => $mainSurvey->id,
            'source_type' => AnalysisSynthesisItem::SOURCE_LECTURER_SURVEY,
        ]);
        $this->assertDatabaseHas('analysis_synthesis_items', [
            'survey_id' => $mainSurvey->id,
            'source_type' => AnalysisSynthesisItem::SOURCE_PRACTITIONER_INTERVIEW,
        ]);
        $this->assertDatabaseHas('analysis_synthesis_items', [
            'survey_id' => $mainSurvey->id,
            'source_type' => AnalysisSynthesisItem::SOURCE_PRACTITIONER_INTERVIEW,
            'theme' => AnalysisSynthesisItem::THEME_SCENE_PRIORITY,
            'finding' => 'Praktisi memprioritaskan Hygiene dan gowning dalam rancangan PharmVR.',
        ]);
    }

    public function test_existing_student_analysis_dashboard_still_works(): void
    {
        [$owner, $mainSurvey] = $this->mainSurveyFixture();

        $this->actingAs($owner)
            ->get(route('admin.surveys.analysis.index', ['survey' => $mainSurvey]))
            ->assertOk()
            ->assertSeeText('ADDIE Analysis Dashboard')
            ->assertSeeText('Survey Response Summary');
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
            'title' => 'Analisa Sistem',
            'description' => 'Student needs analysis questionnaire for PharmVR Analysis ADDIE stage.',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
            'instrument_type' => Survey::INSTRUMENT_ANALYSIS_STUDENT,
            'analysis_group_key' => Survey::ANALYSIS_GROUP_PHARMVR_ADDIE,
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

        return [$owner, $survey->fresh()];
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function response(Survey $survey, array $answers): void
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
}
