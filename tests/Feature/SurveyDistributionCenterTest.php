<?php

namespace Tests\Feature;

use App\Models\ExpertValidator;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyDistributionBatch;
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
use App\Modules\Surveys\Actions\PublishSurveyAction;
use App\Modules\Surveys\Support\SurveyIntroTemplates;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyDistributionCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_distribution_center_with_student_link_and_missing_ctas(): void
    {
        [$admin, $survey] = $this->surveyFixture();
        app(PublishSurveyAction::class)->handle($admin, $survey);

        $this->actingAs($admin)
            ->get(route('admin.surveys.distribution.index', ['survey' => $survey->fresh()]))
            ->assertOk()
            ->assertSeeText('Research Distribution Center')
            ->assertSeeText('Student Questionnaire')
            ->assertSee(route('survey.show', ['survey' => $survey->fresh()->slug]), false)
            ->assertSeeText('Create Lecturer Questionnaire')
            ->assertSeeText('Create Practitioner Interview Form')
            ->assertSeeText('Copy WhatsApp Message')
            ->assertSeeText('Supervisor Review Package')
            ->assertDontSee('token_hash');
    }

    public function test_distribution_center_shows_generated_lecturer_and_practitioner_instruments(): void
    {
        [$admin, $survey] = $this->surveyFixture();
        $lecturer = app(CreateLecturerNeedsAnalysisQuestionnaireAction::class)->handle($admin, $survey);
        $practitioner = app(CreatePractitionerInterviewFormAction::class)->handle($admin, $survey->fresh());
        app(PublishSurveyAction::class)->handle($admin, $lecturer);
        app(PublishSurveyAction::class)->handle($admin, $practitioner);

        $this->actingAs($admin)
            ->get(route('admin.surveys.distribution.index', ['survey' => $survey->fresh()]))
            ->assertOk()
            ->assertSeeText('Kuesioner Analisis Kebutuhan Dosen terhadap Media Pembelajaran Virtual Reality')
            ->assertSeeText('Pedoman Wawancara Praktisi/Ahli CPOB untuk Analisis Kebutuhan PharmVR')
            ->assertSee(route('survey.show', ['survey' => $lecturer->fresh()->slug]), false)
            ->assertSee(route('survey.show', ['survey' => $practitioner->fresh()->slug]), false)
            ->assertSeeText('Masukan Bapak/Ibu akan menjadi dasar pengembangan konten')
            ->assertSeeText('Identitas dapat menggunakan inisial');
    }

    public function test_distribution_report_loads_with_templates_and_safe_token_note(): void
    {
        [$admin, $survey] = $this->surveyFixture();
        $this->validationFixture($admin, $survey);
        $this->readabilityFixture($admin, $survey);

        $this->actingAs($admin)
            ->get(route('admin.surveys.distribution.report', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Research Distribution Package')
            ->assertSeeText('Instrument Links')
            ->assertSeeText('Validation Summary')
            ->assertSeeText('Readability Summary')
            ->assertSeeText('Supervisor Package')
            ->assertSeeText('Raw tokens are not stored')
            ->assertDontSee('token_hash');
    }

    public function test_admin_can_update_manual_distribution_batch_status_and_deadline(): void
    {
        [$admin, $survey] = $this->surveyFixture();

        $this->actingAs($admin)
            ->put(route('admin.surveys.distribution.batches.update', [
                'survey' => $survey,
                'audience' => SurveyDistributionBatch::AUDIENCE_STUDENT,
            ]), [
                'title' => 'Mahasiswa Batch 1',
                'message_subject' => 'Undangan Kuesioner PharmVR',
                'message_body' => 'Mohon mengisi kuesioner PharmVR.',
                'deadline' => today()->addDays(7)->toDateString(),
                'status' => SurveyDistributionBatch::STATUS_SENT_MANUALLY,
                'notes' => 'Shared manually via WhatsApp group.',
            ])
            ->assertRedirect(route('admin.surveys.distribution.index', ['survey' => $survey]));

        $this->assertDatabaseHas('survey_distribution_batches', [
            'survey_id' => $survey->id,
            'audience_type' => SurveyDistributionBatch::AUDIENCE_STUDENT,
            'status' => SurveyDistributionBatch::STATUS_SENT_MANUALLY,
            'notes' => 'Shared manually via WhatsApp group.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.surveys.distribution.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Sent manually')
            ->assertSeeText('Shared manually via WhatsApp group.');
    }

    public function test_validation_and_readability_links_can_be_regenerated_safely_from_distribution_center(): void
    {
        [$admin, $survey] = $this->surveyFixture();
        $assignment = $this->validationFixture($admin, $survey);
        $participant = $this->readabilityFixture($admin, $survey);

        $this->actingAs($admin)
            ->post(route('admin.surveys.distribution.validation.generate-link', ['survey' => $survey, 'assignment' => $assignment]))
            ->assertRedirect(route('admin.surveys.distribution.index', ['survey' => $survey]))
            ->assertSessionHas('generated_validation_url');

        $this->assertNotNull($assignment->fresh()->token_hash);

        $this->actingAs($admin)
            ->post(route('admin.surveys.distribution.readability.generate-link', ['survey' => $survey, 'participant' => $participant]))
            ->assertRedirect(route('admin.surveys.distribution.index', ['survey' => $survey]))
            ->assertSessionHas('generated_readability_url');

        $this->assertNotNull($participant->fresh()->token_hash);

        $this->actingAs($admin)
            ->get(route('admin.surveys.distribution.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Regenerate Link')
            ->assertDontSee($assignment->fresh()->token_hash)
            ->assertDontSee($participant->fresh()->token_hash);
    }

    public function test_distribution_center_is_not_public_and_existing_public_survey_still_works(): void
    {
        [$admin, $survey] = $this->surveyFixture();
        app(PublishSurveyAction::class)->handle($admin, $survey);

        $this->get(route('admin.surveys.distribution.index', ['survey' => $survey]))
            ->assertRedirect('/admin/login');

        $this->get(route('survey.show', ['survey' => $survey->fresh()->slug]))
            ->assertOk()
            ->assertSeeText('Pengantar Kuesioner Analisis Kebutuhan PharmVR')
            ->assertDontSeeText('Research Distribution Center');

        $this->post(route('survey.responses.store', ['survey' => $survey->slug]), [
            'intro_consent' => '1',
            'answers' => [
                'student_need' => '5',
            ],
        ])
            ->assertOk()
            ->assertSee('Response submitted');

        $this->assertSame(1, SurveyResponse::count());
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

        $survey->questions()->create([
            'question_key' => 'student_need',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Mahasiswa membutuhkan PharmVR untuk memahami CPOB.',
            'options' => ['scale' => [1, 2, 3, 4, 5]],
            'is_required' => true,
            'sort_order' => 1,
        ]);

        return [$admin, $survey->fresh()];
    }

    private function validationFixture(User $admin, Survey $survey): SurveyValidationAssignment
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
            'instructions' => 'Mohon menilai kualitas butir instrumen.',
        ]);

        $validator = ExpertValidator::create([
            'created_by' => $admin->id,
            'name' => 'Validator CPOB',
            'email' => 'validator@example.test',
            'institution' => 'Faculty',
            'is_active' => true,
        ]);

        return SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->id,
            'expert_validator_id' => $validator->id,
            'role' => 'content_expert',
            'status' => SurveyValidationAssignment::STATUS_PENDING,
            'created_by' => $admin->id,
        ]);
    }

    private function readabilityFixture(User $admin, Survey $survey): SurveyReadabilityParticipant
    {
        $round = SurveyReadabilityRound::create([
            'survey_id' => $survey->id,
            'created_by' => $admin->id,
            'title' => 'Uji Keterbacaan Awal',
            'status' => SurveyReadabilityRound::STATUS_OPEN,
            'target_participants' => 5,
            'instructions' => 'Nilai keterbacaan instrumen.',
        ]);

        return SurveyReadabilityParticipant::create([
            'survey_readability_round_id' => $round->id,
            'survey_id' => $survey->id,
            'participant_name' => 'Pilot Student',
            'participant_email' => 'pilot@example.test',
            'participant_type' => SurveyReadabilityParticipant::TYPE_STUDENT,
            'status' => SurveyReadabilityParticipant::STATUS_PENDING,
        ]);
    }
}
