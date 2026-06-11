<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ProjectMember;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyIndicator;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionScoring;
use App\Models\SurveyResponse;
use App\Models\SurveyScale;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyScoringConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoring_models_use_uuid_primary_keys(): void
    {
        $this->assertFalse((new SurveyScale)->getIncrementing());
        $this->assertSame('string', (new SurveyScale)->getKeyType());
        $this->assertFalse((new SurveyIndicator)->getIncrementing());
        $this->assertSame('string', (new SurveyIndicator)->getKeyType());
        $this->assertFalse((new SurveyQuestionScoring)->getIncrementing());
        $this->assertSame('string', (new SurveyQuestionScoring)->getKeyType());
    }

    public function test_owner_can_manage_scoring_config_and_activity_is_logged(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $survey, $question] = $this->surveyFixture();

        $scaleResponse = $this->actingAs($owner)
            ->post(route('admin.surveys.scoring.scales.store', ['survey' => $survey]), [
                'name' => 'Usability',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.surveys.scoring.index', ['survey' => $survey]));
        $scale = SurveyScale::firstOrFail();

        $this->actingAs($owner)
            ->post(route('admin.surveys.scoring.indicators.store', ['survey' => $survey]), [
                'survey_scale_id' => $scale->id,
                'name' => 'Ease of Use',
                'interpretation_rules_json' => '[{"min":1,"max":3.4,"label":"Sedang"},{"min":3.41,"max":5,"label":"Tinggi"}]',
            ])
            ->assertRedirect(route('admin.surveys.scoring.index', ['survey' => $survey]));
        $indicator = SurveyIndicator::firstOrFail();

        $this->actingAs($owner)
            ->put(route('admin.surveys.scoring.questions.update', ['survey' => $survey, 'question' => $question]), [
                'survey_indicator_id' => $indicator->id,
                'is_scored' => '1',
                'score_min' => 1,
                'score_max' => 5,
                'weight' => 1.5,
                'is_reverse_scored' => '1',
            ])
            ->assertRedirect(route('admin.surveys.scoring.index', ['survey' => $survey]));

        $this->assertDatabaseHas('survey_scales', ['survey_id' => $survey->id, 'name' => 'Usability']);
        $this->assertDatabaseHas('survey_indicators', ['survey_id' => $survey->id, 'name' => 'Ease of Use']);
        $this->assertDatabaseHas('survey_question_scorings', [
            'survey_id' => $survey->id,
            'survey_question_id' => $question->id,
            'survey_indicator_id' => $indicator->id,
            'is_scored' => true,
            'is_reverse_scored' => true,
        ]);

        $this->assertDatabaseHas('activity_logs', ['action' => 'survey.scoring_scale_created']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'survey.scoring_indicator_created']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'survey.question_scoring_updated']);

        $metadata = json_encode(ActivityLog::where('action', 'survey.question_scoring_updated')->firstOrFail()->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('answer_value', $metadata);
        $this->assertStringNotContainsString('respondent', mb_strtolower($metadata));
        $this->assertNotNull($scaleResponse);
    }

    public function test_scoring_config_routes_are_authenticated_and_policy_protected(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $survey] = $this->surveyFixture();
        $viewer = User::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $viewer->id,
            'role' => ProjectMember::ROLE_VIEWER,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);

        $route = route('admin.surveys.scoring.index', ['survey' => $survey]);

        $this->get($route)->assertRedirect('/admin/login');
        $this->actingAs($viewer)->get($route)->assertForbidden();
    }

    public function test_hidden_questions_cannot_be_scored_and_config_changes_lock_after_submitted_responses(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $survey] = $this->surveyFixture();
        $hidden = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'hidden_token',
            'type' => SurveyQuestion::TYPE_HIDDEN,
            'label' => 'Hidden token',
        ]);
        $indicator = $survey->indicators()->create([
            'name' => 'Hidden Guard',
            'slug' => 'hidden-guard',
        ]);

        $this->actingAs($owner)
            ->put(route('admin.surveys.scoring.questions.update', ['survey' => $survey, 'question' => $hidden]), [
                'survey_indicator_id' => $indicator->id,
                'is_scored' => '1',
            ])
            ->assertSessionHasErrors('is_scored');

        SurveyResponse::create([
            'survey_id' => $survey->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('admin.surveys.scoring.scales.store', ['survey' => $survey]), ['name' => 'Locked Scale'])
            ->assertSessionHasErrors('scoring');
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: Survey, 3?: SurveyQuestion}
     */
    private function surveyFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Scoring Config Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Scoring Config Survey',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);
        $question = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'ease',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Ease',
            'settings' => ['scale' => [1, 2, 3, 4, 5]],
        ]);

        return [$owner, $project, $survey, $question];
    }
}
