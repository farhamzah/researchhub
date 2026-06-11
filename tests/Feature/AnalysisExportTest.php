<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AnalysisResult;
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

class AnalysisExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_export_analysis_tables_as_csv(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $result] = $this->analysisResultFixture();

        $response = $this->actingAs($owner)
            ->get(route('admin.analysis.export.csv', ['analysisResult' => $result]))
            ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('tables.csv', $response->headers->get('content-disposition'));

        [$header] = $this->csvRows($response->getContent());

        $this->assertSame([
            'analysis_result_id',
            'table_key',
            'table_title',
            'row_number',
            'metric',
            'value',
            'percentage',
            'question_key',
            'question_type',
        ], $header);

        $this->assertStringContainsString('question_descriptive_summary', $response->getContent());
        $this->assertStringContainsString('answered_count', $response->getContent());
        $this->assertStringContainsString('feedback', $response->getContent());
        $this->assertStringNotContainsString('Farhan Respondent', $response->getContent());
        $this->assertStringNotContainsString('farhan@example.test', $response->getContent());
        $this->assertStringNotContainsString('SECRET-HIDDEN', $response->getContent());

        $log = ActivityLog::where('action', 'analysis.exported')->firstOrFail();
        $this->assertSame($result->id, $log->metadata['analysis_result_id']);
        $this->assertSame($result->analysis_job_id, $log->metadata['analysis_job_id']);
        $this->assertSame($result->survey_id, $log->metadata['survey_id']);
        $this->assertSame('csv', $log->metadata['format']);

        $metadata = json_encode($log->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('feedback', $metadata);
        $this->assertStringNotContainsString('Farhan Respondent', $metadata);
    }

    public function test_export_routes_are_authenticated_and_policy_protected(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $result] = $this->analysisResultFixture();
        $viewer = User::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $viewer->id,
            'role' => ProjectMember::ROLE_VIEWER,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);

        $this->get(route('admin.analysis.export.csv', ['analysisResult' => $result]))
            ->assertRedirect('/admin/login');

        $this->actingAs($viewer)
            ->get(route('admin.analysis.export.csv', ['analysisResult' => $result]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('admin.analysis.export.markdown', ['analysisResult' => $result]))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: AnalysisResult}
     */
    private function analysisResultFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Export Draft Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Export Draft Survey',
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
        ]);
        $respondent = Respondent::create([
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'name' => 'Farhan Respondent',
            'email' => 'farhan@example.test',
            'identifier' => 'NIM-001',
        ]);
        $surveyResponse = SurveyResponse::create([
            'survey_id' => $survey->id,
            'respondent_id' => $respondent->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
        SurveyAnswer::create([
            'survey_response_id' => $surveyResponse->id,
            'survey_question_id' => $feedback->id,
            'question_key' => 'feedback',
            'answer_value' => 'Aman untuk draft',
        ]);
        SurveyAnswer::create([
            'survey_response_id' => $surveyResponse->id,
            'survey_question_id' => $hidden->id,
            'question_key' => 'hidden_token',
            'answer_value' => 'SECRET-HIDDEN',
        ]);

        return [$owner, $project, app(RunSurveyDescriptiveAnalysisAction::class)->handle($owner, $survey)];
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function csvRows(string $csv): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
