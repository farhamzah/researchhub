<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Respondent;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Analysis\Services\SurveyIndicatorScoringService;
use App\Modules\Analysis\Services\SurveyScaleScoringService;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyIndicatorScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_indicator_scoring_supports_reverse_scoring_weighting_and_interpretation(): void
    {
        [$survey, $questions] = $this->scoredSurveyFixture();

        $indicatorSummary = app(SurveyIndicatorScoringService::class)->summarize($survey);
        $scaleSummary = app(SurveyScaleScoringService::class)->summarize($survey, $indicatorSummary);

        $summary = collect($indicatorSummary)->firstWhere('indicator_name', 'Ease of Use');

        $this->assertSame(2, $summary['item_count']);
        $this->assertSame(2, $summary['respondent_count']);
        $this->assertSame(3.0, $summary['mean']);
        $this->assertSame(3.0, $summary['median']);
        $this->assertSame(2.0, $summary['min']);
        $this->assertSame(4.0, $summary['max']);
        $this->assertSame(1.0, $summary['standard_deviation']);
        $this->assertSame(0, $summary['missing_count']);
        $this->assertSame('Sedang', $summary['interpretation_label']);
        $this->assertCount(2, $summary['respondent_scores']);

        $scale = collect($scaleSummary)->firstWhere('scale_name', 'Usability');
        $this->assertSame(1, $scale['indicator_count']);
        $this->assertSame(2, $scale['item_count']);
        $this->assertSame(3.0, $scale['mean']);
    }

    public function test_single_choice_scores_are_read_from_configuration_and_hidden_questions_are_ignored(): void
    {
        [$survey, $questions] = $this->scoredSurveyFixture(includeChoice: true, includeHiddenScoring: true);

        $indicatorSummary = app(SurveyIndicatorScoringService::class)->summarize($survey);
        $summary = collect($indicatorSummary)->firstWhere('indicator_name', 'Choice Score');

        $this->assertSame(1, $summary['item_count']);
        $this->assertSame(2, $summary['respondent_count']);
        $this->assertSame(2.5, $summary['mean']);

        $ease = collect($indicatorSummary)->firstWhere('indicator_name', 'Ease of Use');
        $this->assertSame(2, $ease['item_count']);
        $this->assertStringNotContainsString('hidden_token', json_encode($indicatorSummary, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('SECRET-HIDDEN', json_encode($indicatorSummary, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{0: Survey, 1: array<string, SurveyQuestion>}
     */
    private function scoredSurveyFixture(bool $includeChoice = false, bool $includeHiddenScoring = false): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Indicator Scoring Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Indicator Scoring Survey',
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
                ['min' => 1, 'max' => 2.5, 'label' => 'Rendah'],
                ['min' => 2.51, 'max' => 3.5, 'label' => 'Sedang'],
                ['min' => 3.51, 'max' => 5, 'label' => 'Tinggi'],
            ],
        ]);
        $ease = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'ease',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Ease',
            'settings' => ['scale' => [1, 2, 3, 4, 5]],
        ]);
        $burden = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'burden',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Burden',
            'settings' => ['scale' => [1, 2, 3, 4, 5]],
        ]);

        $ease->scoring()->create([
            'survey_id' => $survey->id,
            'survey_indicator_id' => $indicator->id,
            'is_scored' => true,
            'score_min' => 1,
            'score_max' => 5,
            'weight' => 2,
        ]);
        $burden->scoring()->create([
            'survey_id' => $survey->id,
            'survey_indicator_id' => $indicator->id,
            'is_scored' => true,
            'score_min' => 1,
            'score_max' => 5,
            'weight' => 1,
            'is_reverse_scored' => true,
        ]);

        $questions = ['ease' => $ease, 'burden' => $burden];
        if ($includeChoice) {
            $choiceIndicator = $survey->indicators()->create([
                'name' => 'Choice Score',
                'slug' => 'choice-score',
            ]);
            $choice = SurveyQuestion::create([
                'survey_id' => $survey->id,
                'question_key' => 'choice',
                'type' => SurveyQuestion::TYPE_SINGLE_CHOICE,
                'label' => 'Choice',
                'options' => ['choices' => ['low', 'high']],
            ]);
            $choice->scoring()->create([
                'survey_id' => $survey->id,
                'survey_indicator_id' => $choiceIndicator->id,
                'settings' => ['scores' => ['low' => 1, 'high' => 4]],
            ]);
            $questions['choice'] = $choice;
        }

        $hidden = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'hidden_token',
            'type' => SurveyQuestion::TYPE_HIDDEN,
            'label' => 'Hidden token',
        ]);
        if ($includeHiddenScoring) {
            $hidden->scoring()->create([
                'survey_id' => $survey->id,
                'survey_indicator_id' => $indicator->id,
                'is_scored' => true,
                'weight' => 99,
            ]);
        }

        $this->responseWithAnswers($survey, $questions, $hidden, 5, 4, $includeChoice ? 'high' : null);
        $this->responseWithAnswers($survey, $questions, $hidden, 1, 2, $includeChoice ? 'low' : null);

        return [$survey->fresh(['scales', 'indicators.scale', 'questionScorings.question']), $questions];
    }

    /**
     * @param  array<string, SurveyQuestion>  $questions
     */
    private function responseWithAnswers(Survey $survey, array $questions, SurveyQuestion $hidden, int $ease, int $burden, ?string $choice): void
    {
        $respondent = Respondent::create([
            'project_id' => $survey->project_id,
            'survey_id' => $survey->id,
            'name' => 'Private Respondent',
            'email' => 'private@example.test',
        ]);
        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'respondent_id' => $respondent->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        foreach (['ease' => $ease, 'burden' => $burden] as $key => $value) {
            SurveyAnswer::create([
                'survey_response_id' => $response->id,
                'survey_question_id' => $questions[$key]->id,
                'question_key' => $key,
                'answer_value' => $value,
            ]);
        }

        if ($choice !== null) {
            SurveyAnswer::create([
                'survey_response_id' => $response->id,
                'survey_question_id' => $questions['choice']->id,
                'question_key' => 'choice',
                'answer_value' => $choice,
            ]);
        }

        SurveyAnswer::create([
            'survey_response_id' => $response->id,
            'survey_question_id' => $hidden->id,
            'question_key' => 'hidden_token',
            'answer_value' => 'SECRET-HIDDEN',
        ]);
    }
}
