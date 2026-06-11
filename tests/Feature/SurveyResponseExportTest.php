<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ResearchProject;
use App\Models\Respondent;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyResponseExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_exports_privacy_safe_csv_with_question_columns_and_normalized_answers(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $survey] = $this->responseFixture();

        $response = $this->actingAs($owner)
            ->get(route('admin.surveys.responses.export', ['survey' => $survey]))
            ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('responses.csv', $response->headers->get('content-disposition'));

        [$header, $row] = $this->csvRows($response->getContent());

        $this->assertContains('response_id', $header);
        $this->assertContains('submitted_at', $header);
        $this->assertContains('status', $header);
        $this->assertContains('respondent_display', $header);
        $this->assertContains('pseudonym_code', $header);
        $this->assertContains('feedback', $header);
        $this->assertContains('choices', $header);
        $this->assertContains('consent', $header);
        $this->assertContains('matrix', $header);
        $this->assertNotContains('tracking_code', $header);
        $this->assertNotContains('respondent_name', $header);
        $this->assertNotContains('respondent_email', $header);

        $indexed = array_combine($header, $row);

        $this->assertSame('submitted', $indexed['status']);
        $this->assertSame('fa****@example.test', $indexed['respondent_display']);
        $this->assertSame('R-001', $indexed['pseudonym_code']);
        $this->assertSame('Helpful interface', $indexed['feedback']);
        $this->assertSame('fast | simple', $indexed['choices']);
        $this->assertSame('yes', $indexed['consent']);
        $this->assertJson($indexed['matrix']);
        $this->assertStringNotContainsString('Farhan Respondent', $response->getContent());
        $this->assertStringNotContainsString('farhan@example.test', $response->getContent());
        $this->assertStringNotContainsString('SECRET-TRACKING', $response->getContent());

        $log = ActivityLog::where('action', 'survey.responses_exported')->firstOrFail();
        $this->assertSame($survey->id, $log->metadata['survey_id']);
        $this->assertSame(1, $log->metadata['response_count']);
        $this->assertFalse($log->metadata['with_identity']);

        $metadata = json_encode($log->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Helpful interface', $metadata);
        $this->assertStringNotContainsString('farhan@example.test', $metadata);
    }

    public function test_response_index_shows_export_links_and_hides_identity_export_without_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $survey] = $this->responseFixture();

        $this->actingAs($owner)
            ->get(route('admin.surveys.responses.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee('Export CSV Table Data')
            ->assertDontSee('Export CSV with Respondent Identity');

        $owner->givePermissionTo('surveys.view_respondent_identity');

        $this->actingAs($owner)
            ->get(route('admin.surveys.responses.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee('Identity Export')
            ->assertSee('Export CSV with Respondent Identity');
    }

    /**
     * @return array{0: User, 1: Survey}
     */
    private function responseFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Export Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Export Survey',
            'identity_mode' => Survey::IDENTITY_FULL,
        ]);

        $questions = collect([
            'feedback' => SurveyQuestion::TYPE_LONG_TEXT,
            'choices' => SurveyQuestion::TYPE_MULTIPLE_CHOICE,
            'consent' => SurveyQuestion::TYPE_CONSENT,
            'matrix' => SurveyQuestion::TYPE_LIKERT_MATRIX,
            'tracking_code' => SurveyQuestion::TYPE_HIDDEN,
        ])->map(function (string $type, string $key) use ($survey): SurveyQuestion {
            return SurveyQuestion::create([
                'survey_id' => $survey->id,
                'question_key' => $key,
                'type' => $type,
                'label' => str_replace('_', ' ', $key),
                'sort_order' => $key === 'tracking_code' ? 99 : 1,
            ]);
        });

        $respondent = Respondent::create([
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'pseudonym_code' => 'R-001',
            'name' => 'Farhan Respondent',
            'email' => 'farhan@example.test',
            'identifier' => 'NIM-001',
            'institution' => 'ResearchHub University',
        ]);
        $surveyResponse = SurveyResponse::create([
            'survey_id' => $survey->id,
            'respondent_id' => $respondent->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $answers = [
            'feedback' => 'Helpful interface',
            'choices' => ['fast', 'simple'],
            'consent' => true,
            'matrix' => ['ease' => 5, 'trust' => 4],
            'tracking_code' => 'SECRET-TRACKING',
        ];

        foreach ($answers as $key => $value) {
            SurveyAnswer::create([
                'survey_response_id' => $surveyResponse->id,
                'survey_question_id' => $questions[$key]->id,
                'question_key' => $key,
                'answer_value' => $value,
            ]);
        }

        return [$owner, $survey];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
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
