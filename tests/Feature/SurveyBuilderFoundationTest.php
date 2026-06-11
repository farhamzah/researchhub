<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ResearchProject;
use App\Models\Respondent;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Surveys\Actions\CloseSurveyAction;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SurveyBuilderFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_survey_domain_uses_uuid_models_and_configured_question_types(): void
    {
        [$owner, $project] = $this->projectFixture();
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Survey kebutuhan mahasiswa',
        ]);
        $page = SurveyPage::create([
            'survey_id' => $survey->id,
            'title' => 'Kebutuhan',
            'sort_order' => 1,
        ]);
        $question = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'page_id' => $page->id,
            'question_key' => 'need_score',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Kebutuhan media pembelajaran',
            'options' => ['scale' => [1, 2, 3, 4, 5]],
            'is_required' => true,
        ]);
        $respondent = Respondent::create([
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'name' => 'Encrypted Respondent',
            'email' => 'respondent@example.test',
            'identifier' => 'NIM-001',
        ]);
        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'respondent_id' => $respondent->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
        $answer = SurveyAnswer::create([
            'survey_response_id' => $response->id,
            'survey_question_id' => $question->id,
            'question_key' => $question->question_key,
            'answer_value' => 5,
        ]);

        foreach ([$survey, $page, $question, $respondent, $response, $answer] as $model) {
            $this->assertTrue(Str::isUuid($model->getKey()));
        }

        $this->assertSame(Survey::STATUS_DRAFT, $survey->status);
        $this->assertSame(Survey::IDENTITY_HIDDEN, $survey->identity_mode);
        $this->assertContains(SurveyQuestion::TYPE_LIKERT_MATRIX, config('researchhub_surveys.question_types'));

        $rawRespondent = DB::table('respondents')->where('id', $respondent->id)->first();
        $this->assertNotSame('Encrypted Respondent', $rawRespondent->name);
        $this->assertNotSame('respondent@example.test', $rawRespondent->email);
        $this->assertNotSame('NIM-001', $rawRespondent->identifier);
        $this->assertSame('Encrypted Respondent', $respondent->fresh()->name);
    }

    public function test_survey_status_workflow_and_activity_logs_exist(): void
    {
        [$owner, $project] = $this->projectFixture();
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Validasi ahli media',
            'identity_mode' => Survey::IDENTITY_PSEUDONYM,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'survey.created',
            'subject_id' => $survey->id,
        ]);

        $published = app(PublishSurveyAction::class)->handle($owner, $survey);

        $this->assertSame(Survey::STATUS_PUBLISHED, $published->status);
        $this->assertTrue($published->is_public);
        $this->assertTrue($published->canReceiveResponses());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'survey.published',
            'subject_id' => $survey->id,
        ]);

        $closed = app(CloseSurveyAction::class)->handle($owner, $published);

        $this->assertSame(Survey::STATUS_CLOSED, $closed->status);
        $this->assertFalse($closed->is_public);
        $this->assertFalse($closed->canReceiveResponses());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'survey.closed',
            'subject_id' => $survey->id,
        ]);
        $this->assertSame(3, ActivityLog::where('subject_id', $survey->id)->count());
    }

    /**
     * @return array{0: User, 1: ResearchProject}
     */
    private function projectFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Survey Foundation Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        return [$owner, $project];
    }
}
