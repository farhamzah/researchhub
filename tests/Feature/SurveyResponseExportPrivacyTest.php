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
use App\Modules\Surveys\Actions\CreateSurveyAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyResponseExportPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_users_cannot_export_responses(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $survey] = $this->responseFixture();

        $this->get(route('admin.surveys.responses.export', ['survey' => $survey]))
            ->assertRedirect();
    }

    public function test_viewer_project_member_cannot_export_responses(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $survey] = $this->responseFixture();
        $viewer = User::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $viewer->id,
            'role' => ProjectMember::ROLE_VIEWER,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('admin.surveys.responses.export', ['survey' => $survey]))
            ->assertForbidden();
    }

    public function test_identity_export_is_blocked_without_identity_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $survey] = $this->responseFixture();

        $this->actingAs($owner)
            ->get(route('admin.surveys.responses.export', ['survey' => $survey, 'with_identity' => 1]))
            ->assertForbidden();
    }

    public function test_owner_with_permission_can_export_identity_csv(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $survey] = $this->responseFixture();
        $owner->givePermissionTo('surveys.view_respondent_identity');

        $response = $this->actingAs($owner)
            ->get(route('admin.surveys.responses.export', ['survey' => $survey, 'with_identity' => 1]))
            ->assertOk();

        [$header, $row] = $this->csvRows($response->getContent());
        $indexed = array_combine($header, $row);

        $this->assertContains('respondent_name', $header);
        $this->assertContains('respondent_email', $header);
        $this->assertContains('respondent_identifier', $header);
        $this->assertContains('respondent_institution', $header);
        $this->assertSame('Farhan Respondent', $indexed['respondent_name']);
        $this->assertSame('farhan@example.test', $indexed['respondent_email']);
        $this->assertSame('NIM-001', $indexed['respondent_identifier']);
        $this->assertSame('ResearchHub University', $indexed['respondent_institution']);
    }

    public function test_super_admin_can_export_identity_csv(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $survey] = $this->responseFixture();
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.surveys.responses.export', ['survey' => $survey, 'with_identity' => 1]))
            ->assertOk();

        $this->assertStringContainsString('Farhan Respondent', $response->getContent());
        $this->assertStringContainsString('farhan@example.test', $response->getContent());
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: Survey}
     */
    private function responseFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Export Privacy Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Export Privacy Survey',
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
            'institution' => 'ResearchHub University',
        ]);
        $surveyResponse = SurveyResponse::create([
            'survey_id' => $survey->id,
            'respondent_id' => $respondent->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
        SurveyAnswer::create([
            'survey_response_id' => $surveyResponse->id,
            'survey_question_id' => $question->id,
            'question_key' => 'feedback',
            'answer_value' => 'Helpful interface',
        ]);

        return [$owner, $project, $survey];
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
