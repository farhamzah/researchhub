<?php

namespace Tests\Feature;

use App\Models\ProjectMember;
use App\Models\ResearchProject;
use App\Models\Respondent;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Analysis\Actions\RunSurveyDescriptiveAnalysisAction;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_routes_are_authenticated_and_owner_scoped(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $survey] = $this->privacyFixture();
        $viewer = User::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $viewer->id,
            'role' => ProjectMember::ROLE_VIEWER,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);

        $this->get(route('admin.surveys.analysis.index', ['survey' => $survey]))
            ->assertRedirect('/admin/login');

        $this->actingAs($viewer)
            ->get(route('admin.surveys.analysis.index', ['survey' => $survey]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('admin.surveys.analysis.run', ['survey' => $survey]))
            ->assertForbidden();

        $result = app(RunSurveyDescriptiveAnalysisAction::class)->handle($owner, $survey);

        $this->actingAs($viewer)
            ->get(route('admin.analysis.results.show', ['analysisResult' => $result]))
            ->assertForbidden();
    }

    public function test_analysis_payload_and_narrative_exclude_identity_and_hidden_answers(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $survey] = $this->privacyFixture();

        $result = app(RunSurveyDescriptiveAnalysisAction::class)->handle($owner, $survey);
        $payload = json_encode($result->result_payload, JSON_THROW_ON_ERROR);
        $narrative = $result->narratives->firstOrFail()->narrative;

        $this->assertStringNotContainsString('Farhan Respondent', $payload);
        $this->assertStringNotContainsString('farhan@example.test', $payload);
        $this->assertStringNotContainsString('NIM-001', $payload);
        $this->assertStringNotContainsString('SECRET-HIDDEN', $payload);
        $this->assertStringNotContainsString('Farhan Respondent', $narrative);
        $this->assertStringNotContainsString('farhan@example.test', $narrative);
        $this->assertStringNotContainsString('SECRET-HIDDEN', $narrative);
    }

    public function test_super_admin_can_view_any_analysis_result(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $survey] = $this->privacyFixture();
        $result = app(RunSurveyDescriptiveAnalysisAction::class)->handle($owner, $survey);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->get(route('admin.analysis.results.show', ['analysisResult' => $result]))
            ->assertOk()
            ->assertSee('Analysis Center');
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: Survey}
     */
    private function privacyFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Analysis Privacy Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Analysis Privacy Survey',
            'identity_mode' => Survey::IDENTITY_FULL,
        ]);
        $feedback = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'feedback',
            'type' => SurveyQuestion::TYPE_LONG_TEXT,
            'label' => 'Feedback',
        ]);
        $hidden = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'hidden_token',
            'type' => SurveyQuestion::TYPE_HIDDEN,
            'label' => 'Hidden token',
            'sort_order' => 99,
        ]);
        $respondent = Respondent::create([
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'name' => 'Farhan Respondent',
            'email' => 'farhan@example.test',
            'identifier' => 'NIM-001',
            'institution' => 'ResearchHub University',
        ]);
        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'respondent_id' => $respondent->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
        SurveyAnswer::create([
            'survey_response_id' => $response->id,
            'survey_question_id' => $feedback->id,
            'question_key' => 'feedback',
            'answer_value' => 'Aman dan jelas',
        ]);
        SurveyAnswer::create([
            'survey_response_id' => $response->id,
            'survey_question_id' => $hidden->id,
            'question_key' => 'hidden_token',
            'answer_value' => 'SECRET-HIDDEN',
        ]);

        return [$owner, $project, $survey];
    }
}
