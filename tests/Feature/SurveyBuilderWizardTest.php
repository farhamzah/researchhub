<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\AnalysisResult;
use App\Models\ExpertValidator;
use App\Models\ResearchProject;
use App\Models\Respondent;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use Database\Seeders\MyRisetDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyBuilderWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_survey_builder_renders_wizard_steps_with_meaningful_data(): void
    {
        $this->seed(MyRisetDemoSeeder::class);

        $admin = User::query()->where('email', 'admin@researchhub.test')->firstOrFail();
        $survey = Survey::query()->where('slug', 'angket-evaluasi-pembelajaran-pharmvr')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Setup Survey')
            ->assertSeeText('Indikator')
            ->assertSeeText('Pertanyaan')
            ->assertSeeText('Skoring')
            ->assertSeeText('Preview')
            ->assertSeeText('Validasi Ahli')
            ->assertSeeText('Respons & Analisis')
            ->assertSeeText('Angket Evaluasi Pembelajaran PharmVR')
            ->assertSeeText('Usability dan Kejelasan Media')
            ->assertSeeText('Evaluasi Pembelajaran PharmVR')
            ->assertSeeText('Question List')
            ->assertSeeText('Option count: 4')
            ->assertSeeText('Scoring readiness')
            ->assertSeeText('Admin-only respondent preview')
            ->assertSeeText('Expert validation readiness')
            ->assertSeeText('Responses and analysis status')
            ->assertSeeText('Demo Descriptive Analysis - Angket Evaluasi Pembelajaran PharmVR')
            ->assertDontSee('token_hash')
            ->assertDontSee('response_token_hash')
            ->assertDontSee('/validation/survey/')
            ->assertDontSee('/supervision/review/')
            ->assertDontSee('validator.materi@example.test');
    }

    public function test_admin_preview_does_not_create_survey_responses(): void
    {
        [$owner, $survey] = $this->surveyFixture();
        $survey->update([
            'intro_title' => 'Pengantar Preview Admin',
            'intro_text' => 'Narasi pengantar yang akan dibaca responden sebelum menjawab.',
            'estimated_duration' => '12 menit',
            'privacy_statement' => 'Data responden disimpan rahasia untuk kebutuhan penelitian.',
            'respondent_instruction' => 'Baca setiap butir dan jawab sesuai pengalaman.',
            'consent_text' => 'Saya setuju mengikuti survei ini.',
            'require_consent_before_start' => true,
            'intro_image_alt_text' => 'Ilustrasi responden membaca pengantar',
            'intro_image_caption' => 'Gambar pembuka instrumen',
            'intro_image_source_note' => 'Sumber: Dokumentasi penelitian',
        ]);
        $survey->questions()->create([
            'question_key' => 'preview_question',
            'type' => SurveyQuestion::TYPE_SHORT_TEXT,
            'label' => 'Preview question',
            'sort_order' => 1,
        ]);

        $before = $survey->responses()->count();

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Admin-only respondent preview')
            ->assertSeeText('admin preview no-save - no response will be saved.')
            ->assertSeeText('Preview ini tidak membuat SurveyResponse dan tidak menampilkan data responden.')
            ->assertSeeText('Pengantar Preview Admin')
            ->assertSeeText('12 menit')
            ->assertSeeText('No intro image uploaded.')
            ->assertSeeText('Ilustrasi responden membaca pengantar')
            ->assertSeeText('Gambar pembuka instrumen')
            ->assertSeeText('Sumber: Dokumentasi penelitian')
            ->assertSeeText('Narasi pengantar yang akan dibaca responden sebelum menjawab.')
            ->assertSeeText('Data responden disimpan rahasia untuk kebutuhan penelitian.')
            ->assertSeeText('Baca setiap butir dan jawab sesuai pengalaman.')
            ->assertSeeText('Consent required before questions')
            ->assertSeeText('Saya setuju mengikuti survei ini.')
            ->assertSeeText('Preview question');

        $this->assertSame($before, $survey->responses()->count());
    }

    public function test_response_lock_warning_appears_when_responses_exist(): void
    {
        [$owner, $survey] = $this->surveyFixture();
        $survey->questions()->create([
            'question_key' => 'locked_question',
            'type' => SurveyQuestion::TYPE_SHORT_TEXT,
            'label' => 'Locked question',
            'sort_order' => 1,
        ]);
        SurveyResponse::create([
            'survey_id' => $survey->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Survey ini sudah memiliki respons. Perubahan struktur pertanyaan dibatasi agar data tetap konsisten.')
            ->assertSeeText('Delete locked');
    }

    public function test_admin_can_configure_public_survey_intro_from_builder(): void
    {
        [$owner, $survey] = $this->surveyFixture();

        $this->actingAs($owner)
            ->put(route('admin.surveys.builder.intro.update', ['survey' => $survey]), [
                'intro_title' => 'Pengantar Analisis Kebutuhan',
                'intro_text' => 'Survey ini membantu peneliti memahami kebutuhan responden.',
                'estimated_duration' => '10-15 menit',
                'privacy_statement' => 'Data responden dijaga rahasia.',
                'respondent_instruction' => 'Baca setiap pertanyaan sebelum menjawab.',
                'consent_text' => 'Saya telah membaca penjelasan dan bersedia melanjutkan.',
                'require_consent_before_start' => '1',
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $survey->refresh();

        $this->assertSame('Pengantar Analisis Kebutuhan', $survey->intro_title);
        $this->assertSame('10-15 menit', $survey->estimated_duration);
        $this->assertTrue($survey->require_consent_before_start);
        $this->assertDatabaseHas('activity_logs', ['action' => 'survey.intro_updated']);

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Opening / Introduction')
            ->assertSee('Pengantar Analisis Kebutuhan');
    }

    public function test_unauthorized_user_cannot_view_builder_for_inaccessible_survey(): void
    {
        [, $survey] = $this->surveyFixture();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertForbidden();
    }

    public function test_builder_does_not_render_tokens_or_respondent_identity(): void
    {
        [$owner, $survey, $project] = $this->surveyFixture();
        $question = $survey->questions()->create([
            'question_key' => 'safe_question',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Safe Likert question',
            'settings' => ['scale' => [1, 2, 3, 4]],
            'sort_order' => 1,
        ]);

        $respondent = Respondent::create([
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'name' => 'Private Respondent Name',
            'email' => 'private.respondent@example.test',
            'identifier' => 'PRIVATE-RESPONDENT-ID',
        ]);
        SurveyResponse::create([
            'survey_id' => $survey->id,
            'respondent_id' => $respondent->id,
            'response_token_hash' => hash('sha256', 'raw-response-token'),
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $round = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Sensitive Token Round',
            'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
            'rating_scale_min' => 1,
            'rating_scale_max' => 4,
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);
        $validator = ExpertValidator::create([
            'created_by' => $owner->id,
            'name' => 'Validator Hidden Email',
            'email' => 'hidden.validator@example.test',
            'is_active' => true,
        ]);
        $assignment = SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->id,
            'expert_validator_id' => $validator->id,
            'role' => 'content_expert',
            'status' => SurveyValidationAssignment::STATUS_SUBMITTED,
            'token_hash' => SurveyValidationAssignment::hashToken('raw-validation-token'),
            'submitted_at' => now(),
            'created_by' => $owner->id,
        ]);
        $assignment->scores()->create([
            'survey_question_id' => $question->id,
            'relevance_score' => 4,
            'clarity_score' => 4,
            'language_score' => 4,
            'appropriateness_score' => 4,
            'recommendation' => 'accepted',
        ]);

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Validation scores: 1')
            ->assertDontSee('Private Respondent Name')
            ->assertDontSee('private.respondent@example.test')
            ->assertDontSee('PRIVATE-RESPONDENT-ID')
            ->assertDontSee('raw-response-token')
            ->assertDontSee(hash('sha256', 'raw-response-token'))
            ->assertDontSee('raw-validation-token')
            ->assertDontSee(SurveyValidationAssignment::hashToken('raw-validation-token'))
            ->assertDontSee('hidden.validator@example.test');
    }

    /**
     * @return array{0: User, 1: Survey, 2: ResearchProject}
     */
    private function surveyFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Builder Wizard Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Builder Wizard Survey',
            'description' => 'Instrument setup description.',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);

        $job = AnalysisJob::create([
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'created_by' => $owner->id,
            'type' => AnalysisJob::TYPE_SURVEY_DESCRIPTIVE,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $analysis = AnalysisResult::create([
            'analysis_job_id' => $job->id,
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'type' => AnalysisJob::TYPE_SURVEY_DESCRIPTIVE,
            'title' => 'Builder Wizard Analysis',
            'summary' => ['submitted_count' => 1],
            'result_payload' => [],
        ]);

        $this->assertNotNull($analysis->id);

        return [$owner, $survey, $project];
    }
}
