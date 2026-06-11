<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AnalysisJob;
use App\Models\AnalysisNarrative;
use App\Models\AnalysisResult;
use App\Models\AnalysisTable;
use App\Models\ResearchProject;
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

class SurveyDescriptiveAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_runs_survey_descriptive_analysis_and_stores_uuid_results(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $survey] = $this->analysisFixture();

        $result = app(RunSurveyDescriptiveAnalysisAction::class)->handle($owner, $survey);

        $this->assertFalse((new AnalysisJob)->incrementing);
        $this->assertFalse((new AnalysisResult)->incrementing);
        $this->assertFalse((new AnalysisTable)->incrementing);
        $this->assertFalse((new AnalysisNarrative)->incrementing);
        $this->assertSame(AnalysisJob::STATUS_COMPLETED, $result->job->status);
        $this->assertSame(2, $result->summary['submitted_count']);
        $this->assertSame(4, $result->summary['response_count']);
        $this->assertSame(8, $result->summary['analyzed_question_count']);
        $this->assertSame(1, $result->summary['hidden_question_count']);
        $this->assertCount(3, $result->tables);
        $this->assertCount(1, $result->narratives);
        $this->assertArrayHasKey('indicator_summary', $result->result_payload);
        $this->assertArrayHasKey('scale_summary', $result->result_payload);

        $questions = collect($result->result_payload['questions'])->keyBy('question_key');

        $likert = $questions['ease'];
        $this->assertEquals(4.5, $likert['mean']);
        $this->assertEquals(4.5, $likert['median']);
        $this->assertEquals(4.0, $likert['min']);
        $this->assertEquals(5.0, $likert['max']);
        $this->assertEquals(0.5, $likert['standard_deviation']);

        $number = $questions['age'];
        $this->assertEquals(15.0, $number['mean']);
        $this->assertEquals(15.0, $number['median']);
        $this->assertEquals(10.0, $number['min']);
        $this->assertEquals(20.0, $number['max']);
        $this->assertEquals(5.0, $number['standard_deviation']);

        $singleChoice = collect($questions['program']['frequencies'])->keyBy('value');
        $this->assertSame(1, $singleChoice['A']['count']);
        $this->assertEquals(50.0, $singleChoice['A']['percentage']);
        $this->assertSame(1, $singleChoice['B']['count']);

        $multipleChoice = collect($questions['features']['frequencies'])->keyBy('value');
        $this->assertSame(2, $multipleChoice['fast']['count']);
        $this->assertEquals(100.0, $multipleChoice['fast']['percentage']);
        $this->assertSame(1, $multipleChoice['simple']['count']);
        $this->assertEquals(50.0, $multipleChoice['simple']['percentage']);

        $this->assertSame(1, $questions['consent']['accepted_count']);
        $this->assertSame(1, $questions['consent']['not_accepted_count']);
        $this->assertSame('2026-01-01', $questions['visit_date']['min_date']);
        $this->assertSame('2026-02-01', $questions['visit_date']['max_date']);
        $this->assertSame(2, $questions['matrix']['matrix_summary']['clarity']['agree']);
        $this->assertTrue($questions['matrix']['advanced_flattening_deferred']);
        $this->assertArrayHasKey('sample_answers', $questions['feedback']);
        $this->assertArrayNotHasKey('tracking_code', $questions->all());

        $this->assertDatabaseHas('activity_logs', ['action' => 'analysis.started']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'analysis.completed']);

        $metadata = ActivityLog::where('action', 'analysis.completed')->firstOrFail()->metadata;
        $this->assertSame($survey->id, $metadata['survey_id']);
        $this->assertSame(2, $metadata['response_count']);
        $this->assertArrayNotHasKey('questions', $metadata);
        $this->assertArrayNotHasKey('answers', $metadata);
    }

    public function test_admin_analysis_route_runs_and_displays_result(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $survey] = $this->analysisFixture();

        $this->actingAs($owner)
            ->get(route('admin.surveys.analysis.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee('Analysis Center')
            ->assertSee('Run Descriptive Analysis');

        $this->actingAs($owner)
            ->post(route('admin.surveys.analysis.run', ['survey' => $survey]))
            ->assertRedirect();

        $result = AnalysisResult::firstOrFail();

        $this->actingAs($owner)
            ->get(route('admin.analysis.results.show', ['analysisResult' => $result]))
            ->assertOk()
            ->assertSee('Draf narasi akademik')
            ->assertSee('Question Descriptive Summary');
    }

    /**
     * @return array{0: User, 1: Survey}
     */
    private function analysisFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Analysis Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Analysis Survey',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);

        $questions = collect([
            'feedback' => [SurveyQuestion::TYPE_LONG_TEXT, []],
            'program' => [SurveyQuestion::TYPE_SINGLE_CHOICE, ['choices' => ['A', 'B']]],
            'features' => [SurveyQuestion::TYPE_MULTIPLE_CHOICE, ['choices' => ['fast', 'simple']]],
            'ease' => [SurveyQuestion::TYPE_LIKERT, ['scale' => [1, 2, 3, 4, 5]]],
            'age' => [SurveyQuestion::TYPE_NUMBER, []],
            'visit_date' => [SurveyQuestion::TYPE_DATE, []],
            'consent' => [SurveyQuestion::TYPE_CONSENT, []],
            'matrix' => [SurveyQuestion::TYPE_LIKERT_MATRIX, []],
            'tracking_code' => [SurveyQuestion::TYPE_HIDDEN, []],
        ])->map(function (array $definition, string $key) use ($survey): SurveyQuestion {
            return SurveyQuestion::create([
                'survey_id' => $survey->id,
                'question_key' => $key,
                'type' => $definition[0],
                'label' => str_replace('_', ' ', $key),
                'options' => in_array($definition[0], [SurveyQuestion::TYPE_SINGLE_CHOICE, SurveyQuestion::TYPE_MULTIPLE_CHOICE], true) ? $definition[1] : null,
                'settings' => $definition[0] === SurveyQuestion::TYPE_LIKERT ? $definition[1] : null,
                'sort_order' => $key === 'tracking_code' ? 99 : 1,
            ]);
        });

        $this->response($survey, $questions, SurveyResponse::STATUS_SUBMITTED, [
            'feedback' => 'Helpful interface',
            'program' => 'A',
            'features' => ['fast', 'simple'],
            'ease' => 4,
            'age' => 10,
            'visit_date' => '2026-01-01',
            'consent' => true,
            'matrix' => ['clarity' => 'agree', 'navigation' => 'neutral'],
            'tracking_code' => 'SECRET-TRACKING',
        ]);
        $this->response($survey, $questions, SurveyResponse::STATUS_SUBMITTED, [
            'feedback' => 'Clear flow',
            'program' => 'B',
            'features' => ['fast'],
            'ease' => 5,
            'age' => 20,
            'visit_date' => '2026-02-01',
            'consent' => false,
            'matrix' => ['clarity' => 'agree', 'navigation' => 'agree'],
            'tracking_code' => 'SECRET-TRACKING-2',
        ]);
        $this->response($survey, $questions, SurveyResponse::STATUS_STARTED, [
            'ease' => 1,
            'age' => 99,
            'program' => 'A',
        ]);
        $this->response($survey, $questions, SurveyResponse::STATUS_VOID, [
            'ease' => 1,
            'age' => 100,
            'program' => 'A',
        ]);

        return [$owner, $survey];
    }

    /**
     * @param  array<string, SurveyQuestion>  $questions
     * @param  array<string, mixed>  $answers
     */
    private function response(Survey $survey, iterable $questions, string $status, array $answers): SurveyResponse
    {
        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'status' => $status,
            'submitted_at' => $status === SurveyResponse::STATUS_SUBMITTED ? now() : null,
        ]);

        foreach ($answers as $key => $value) {
            SurveyAnswer::create([
                'survey_response_id' => $response->id,
                'survey_question_id' => $questions[$key]->id,
                'question_key' => $key,
                'answer_value' => $value,
            ]);
        }

        return $response;
    }
}
