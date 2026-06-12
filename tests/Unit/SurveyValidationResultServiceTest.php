<?php

namespace Tests\Unit;

use App\Models\ExpertValidator;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\SurveyValidationScore;
use App\Models\User;
use App\Modules\Validation\Services\SurveyValidationResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyValidationResultServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_aiken_v_cvi_s_cvi_and_item_statuses_are_calculated_for_one_to_four_scale(): void
    {
        [$round, $firstQuestion, $secondQuestion] = $this->roundWithQuestions();
        [$firstAssignment, $secondAssignment] = $this->submittedAssignments($round);

        $this->score($firstAssignment, $firstQuestion, 4, 4, 3, 4, SurveyValidationScore::RECOMMENDATION_ACCEPTED);
        $this->score($secondAssignment, $firstQuestion, 3, 4, 3, 3, SurveyValidationScore::RECOMMENDATION_MINOR_REVISION);
        $this->score($firstAssignment, $secondQuestion, 2, 2, 2, 2, SurveyValidationScore::RECOMMENDATION_MAJOR_REVISION);
        $this->score($secondAssignment, $secondQuestion, 2, 2, 2, 2, SurveyValidationScore::RECOMMENDATION_REJECTED);

        $result = app(SurveyValidationResultService::class)->analyze($round);

        $this->assertSame(2, $result->summary['submitted_count']);
        $this->assertSame(2, $result->summary['question_count']);
        $this->assertSame(0.5, $result->summary['s_cvi_ave']);
        $this->assertSame(0.5, $result->summary['s_cvi_ua']);
        $this->assertSame(1, $result->summary['valid_count']);
        $this->assertSame(1, $result->summary['reject_count']);
        $this->assertSame(0, $result->summary['revise_count']);

        $firstItem = $result->items[0];
        $this->assertSame(0.8333, $firstItem['aiken']['relevance_score']);
        $this->assertSame(1.0, $firstItem['aiken']['clarity_score']);
        $this->assertSame(0.6667, $firstItem['aiken']['language_score']);
        $this->assertSame(0.8333, $firstItem['aiken']['appropriateness_score']);
        $this->assertSame(0.8333, $firstItem['average_aiken_v']);
        $this->assertSame(1.0, $firstItem['i_cvi']);
        $this->assertSame(SurveyValidationResultService::STATUS_VALID, $firstItem['status']);

        $secondItem = $result->items[1];
        $this->assertSame(0.3333, $secondItem['average_aiken_v']);
        $this->assertSame(0.0, $secondItem['i_cvi']);
        $this->assertSame(SurveyValidationResultService::STATUS_REJECT, $secondItem['status']);
        $this->assertSame('CVR requires an explicit essential/not-essential expert judgment and is not calculated for this round.', $result->cvrNote);
        $this->assertStringContainsString('Aiken\'s V sebesar 0.583', $result->narrative);
        $this->assertStringContainsString('S-CVI/Ave sebesar 0.500', $result->narrative);
    }

    public function test_validation_result_service_handles_no_submissions_without_dividing_by_zero(): void
    {
        [$round] = $this->roundWithQuestions();

        $result = app(SurveyValidationResultService::class)->analyze($round);

        $this->assertSame(0, $result->summary['submitted_count']);
        $this->assertNull($result->summary['average_aiken_v']);
        $this->assertNull($result->summary['s_cvi_ave']);
        $this->assertSame(0, $result->summary['valid_count']);
        $this->assertSame(2, $result->summary['no_data_count']);
        $this->assertSame(SurveyValidationResultService::STATUS_NO_DATA, $result->items[0]['status']);
        $this->assertStringContainsString('Belum terdapat penilaian validasi ahli', $result->narrative);
    }

    /**
     * @return array{0: SurveyValidationRound, 1: SurveyQuestion, 2: SurveyQuestion}
     */
    private function roundWithQuestions(): array
    {
        $user = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $user->id,
            'title' => 'Validation Result Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = Survey::create([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'title' => 'Instrumen Penelitian',
            'status' => Survey::STATUS_DRAFT,
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);
        $round = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $project->id,
            'created_by' => $user->id,
            'title' => 'Round 1',
            'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
            'rating_scale_min' => 1,
            'rating_scale_max' => 4,
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);
        $firstQuestion = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'item_1',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Butir pertama',
            'sort_order' => 1,
        ]);
        $secondQuestion = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'item_2',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Butir kedua',
            'sort_order' => 2,
        ]);

        return [$round, $firstQuestion, $secondQuestion];
    }

    /**
     * @return array{0: SurveyValidationAssignment, 1: SurveyValidationAssignment}
     */
    private function submittedAssignments(SurveyValidationRound $round): array
    {
        return collect(['Validator A', 'Validator B'])
            ->map(function (string $name) use ($round): SurveyValidationAssignment {
                $validator = ExpertValidator::create([
                    'created_by' => $round->created_by,
                    'name' => $name,
                    'is_active' => true,
                ]);

                return SurveyValidationAssignment::create([
                    'survey_validation_round_id' => $round->id,
                    'expert_validator_id' => $validator->id,
                    'status' => SurveyValidationAssignment::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                    'created_by' => $round->created_by,
                ]);
            })
            ->values()
            ->all();
    }

    private function score(
        SurveyValidationAssignment $assignment,
        SurveyQuestion $question,
        int $relevance,
        int $clarity,
        int $language,
        int $appropriateness,
        string $recommendation,
    ): void {
        SurveyValidationScore::create([
            'survey_validation_assignment_id' => $assignment->id,
            'survey_question_id' => $question->id,
            'relevance_score' => $relevance,
            'clarity_score' => $clarity,
            'language_score' => $language,
            'appropriateness_score' => $appropriateness,
            'comment' => 'Komentar singkat',
            'recommendation' => $recommendation,
        ]);
    }
}
