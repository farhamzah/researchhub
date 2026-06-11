<?php

namespace Tests\Feature;

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

class AnalysisIndicatorSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_descriptive_analysis_includes_indicator_and_scale_summary_without_identity_or_hidden_answers(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $survey] = $this->analysisFixture();

        $result = app(RunSurveyDescriptiveAnalysisAction::class)->handle($owner, $survey);
        $payload = $result->result_payload;

        $this->assertArrayHasKey('indicator_summary', $payload);
        $this->assertArrayHasKey('scale_summary', $payload);

        $indicator = collect($payload['indicator_summary'])->firstWhere('indicator_name', 'Ease of Use');
        $this->assertSame('Usability', $indicator['scale_name']);
        $this->assertSame(1, $indicator['item_count']);
        $this->assertSame(2, $indicator['respondent_count']);
        $this->assertEquals(4.0, $indicator['mean']);
        $this->assertSame('Tinggi', $indicator['interpretation_label']);
        $this->assertNotEmpty($indicator['respondent_scores']);

        $scale = collect($payload['scale_summary'])->firstWhere('scale_name', 'Usability');
        $this->assertSame(1, $scale['indicator_count']);
        $this->assertEquals(4.0, $scale['mean']);

        $this->assertDatabaseHas('analysis_tables', [
            'analysis_result_id' => $result->id,
            'table_key' => 'indicator_descriptive_summary',
        ]);
        $this->assertDatabaseHas('analysis_tables', [
            'analysis_result_id' => $result->id,
            'table_key' => 'scale_descriptive_summary',
        ]);

        $resultText = json_encode($result->fresh(['tables', 'narratives'])->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Pada indikator Ease of Use', $resultText);
        $this->assertStringNotContainsString('Private Analysis Respondent', $resultText);
        $this->assertStringNotContainsString('private.analysis@example.test', $resultText);
        $this->assertStringNotContainsString('SECRET-HIDDEN', $resultText);
        $this->assertStringNotContainsString('p-value', mb_strtolower($resultText));
        $this->assertStringNotContainsString('signifikan', mb_strtolower($resultText));
        $this->assertStringNotContainsString('kausal', mb_strtolower($resultText));
        $this->assertStringNotContainsString('efektivitas', mb_strtolower($resultText));
    }

    /**
     * @return array{0: User, 1: Survey}
     */
    private function analysisFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Analysis Indicator Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Analysis Indicator Survey',
            'identity_mode' => Survey::IDENTITY_FULL,
        ]);
        $scale = $survey->scales()->create([
            'name' => 'Usability',
            'slug' => 'usability',
        ]);
        $indicator = $survey->indicators()->create([
            'survey_scale_id' => $scale->id,
            'name' => 'Ease of Use',
            'slug' => 'ease-of-use',
            'interpretation_rules' => [
                ['min' => 1, 'max' => 3.4, 'label' => 'Sedang'],
                ['min' => 3.41, 'max' => 5, 'label' => 'Tinggi'],
            ],
        ]);
        $question = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'ease',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Ease',
            'settings' => ['scale' => [1, 2, 3, 4, 5]],
        ]);
        $hidden = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'hidden_token',
            'type' => SurveyQuestion::TYPE_HIDDEN,
            'label' => 'Hidden token',
        ]);
        $question->scoring()->create([
            'survey_id' => $survey->id,
            'survey_indicator_id' => $indicator->id,
            'is_scored' => true,
            'score_min' => 1,
            'score_max' => 5,
            'weight' => 1,
        ]);

        $this->responseWithAnswer($survey, $question, $hidden, 5);
        $this->responseWithAnswer($survey, $question, $hidden, 3);

        return [$owner, $survey];
    }

    private function responseWithAnswer(Survey $survey, SurveyQuestion $question, SurveyQuestion $hidden, int $value): void
    {
        $respondent = Respondent::create([
            'project_id' => $survey->project_id,
            'survey_id' => $survey->id,
            'name' => 'Private Analysis Respondent',
            'email' => 'private.analysis@example.test',
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
            'question_key' => 'ease',
            'answer_value' => $value,
        ]);
        SurveyAnswer::create([
            'survey_response_id' => $response->id,
            'survey_question_id' => $hidden->id,
            'question_key' => 'hidden_token',
            'answer_value' => 'SECRET-HIDDEN',
        ]);
    }
}
