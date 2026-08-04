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
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyResponseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_list_uses_privacy_safe_display_by_default(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $survey, $response] = $this->responseFixture();

        $this->actingAs($owner)
            ->get(route('admin.surveys.responses.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee('Survey Responses')
            ->assertSee('fa****@example.test')
            ->assertDontSee('Farhan Respondent')
            ->assertDontSee('farhan@example.test')
            ->assertSee('CSV Export Column Preview')
            ->assertDontSee('identity_email');
    }

    public function test_response_detail_reveals_full_identity_only_with_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $survey, $response] = $this->responseFixture();

        $this->actingAs($owner)
            ->get(route('admin.surveys.responses.show', ['survey' => $survey, 'response' => $response]))
            ->assertOk()
            ->assertSee('Response Detail')
            ->assertSee('Helpful interface')
            ->assertDontSee('Authorized Identity View')
            ->assertDontSee('Farhan Respondent')
            ->assertDontSee('farhan@example.test');

        $owner->givePermissionTo('surveys.view_respondent_identity');

        $this->actingAs($owner)
            ->get(route('admin.surveys.responses.show', ['survey' => $survey, 'response' => $response]))
            ->assertOk()
            ->assertSee('Authorized Identity View')
            ->assertSee('Farhan Respondent')
            ->assertSee('farhan@example.test');
    }

    public function test_public_survey_confirmation_does_not_expose_identity_or_admin_links(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Private Survey Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Identity Safety Survey',
            'identity_mode' => Survey::IDENTITY_FULL,
        ]);
        $page = SurveyPage::create([
            'survey_id' => $survey->id,
            'title' => 'Identity Safety',
        ]);
        SurveyQuestion::create([
            'survey_id' => $survey->id,
            'page_id' => $page->id,
            'question_key' => 'feedback',
            'type' => SurveyQuestion::TYPE_LONG_TEXT,
            'label' => 'Feedback',
            'is_required' => true,
        ]);
        app(PublishSurveyAction::class)->handle($owner, $survey);

        $this->post(route('survey.responses.store', ['survey' => $survey->slug]), [
            'identity' => [
                'name' => 'Public Respondent',
                'email' => 'public@example.test',
                'identifier' => 'PUBLIC-001',
            ],
            'answers' => [
                'feedback' => 'Public answer',
            ],
        ])
            ->assertOk()
            ->assertSee('Respons berhasil dikirim')
            ->assertDontSee('Public Respondent')
            ->assertDontSee('public@example.test')
            ->assertDontSee('/admin');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'survey.response_submitted',
        ]);

        $logs = ActivityLog::all()
            ->map(fn (ActivityLog $log): string => json_encode($log->toArray(), JSON_THROW_ON_ERROR))
            ->implode("\n");

        $this->assertStringNotContainsString('Public Respondent', $logs);
        $this->assertStringNotContainsString('public@example.test', $logs);
        $this->assertStringNotContainsString('PUBLIC-001', $logs);
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: Survey, 3: SurveyResponse}
     */
    private function responseFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Response Management Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Response Management Survey',
            'identity_mode' => Survey::IDENTITY_FULL,
        ]);
        $question = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'feedback',
            'type' => SurveyQuestion::TYPE_LONG_TEXT,
            'label' => 'Feedback',
        ]);
        $respondent = Respondent::create([
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'name' => 'Farhan Respondent',
            'email' => 'farhan@example.test',
            'identifier' => 'NIM-001',
        ]);
        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'respondent_id' => $respondent->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
        SurveyAnswer::create([
            'survey_response_id' => $response->id,
            'survey_question_id' => $question->id,
            'question_key' => 'feedback',
            'answer_value' => 'Helpful interface',
        ]);

        return [$owner, $project, $survey, $response];
    }
}
