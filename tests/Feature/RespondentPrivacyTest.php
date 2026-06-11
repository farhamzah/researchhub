<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Respondent;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Services\RespondentPrivacyService;
use App\Modules\Surveys\Services\SurveyResponseExportRowBuilder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RespondentPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_service_centralizes_hidden_anonymous_pseudonym_masked_and_full_modes(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project] = $this->projectFixture();
        $service = app(RespondentPrivacyService::class);

        $fullSurvey = $this->survey($owner, $project, Survey::IDENTITY_FULL);
        $respondent = Respondent::create([
            'project_id' => $project->id,
            'survey_id' => $fullSurvey->id,
            'name' => 'Farhan Respondent',
            'email' => 'farhan@example.test',
            'identifier' => 'NIM-001',
            'institution' => 'ResearchHub University',
        ]);

        $this->assertSame('fa****@example.test', $service->display($respondent, $fullSurvey, $owner));
        $this->assertSame('fa****@example.test', $service->display($respondent, $fullSurvey, $owner, RespondentPrivacyService::MODE_MASKED));
        $this->assertSame('Identity hidden', $service->display($respondent, $this->survey($owner, $project, Survey::IDENTITY_HIDDEN), $owner));
        $this->assertSame('Anonymous respondent', $service->display(null, $this->survey($owner, $project, Survey::IDENTITY_ANONYMOUS), $owner));

        $pseudonymSurvey = $this->survey($owner, $project, Survey::IDENTITY_PSEUDONYM);
        $pseudonym = Respondent::create([
            'project_id' => $project->id,
            'survey_id' => $pseudonymSurvey->id,
            'pseudonym_code' => 'R001',
        ]);

        $this->assertSame('R001', $service->display($pseudonym, $pseudonymSurvey, $owner));
        $this->assertSame('Identity hidden', $service->display($respondent, $fullSurvey, $owner, RespondentPrivacyService::MODE_FULL));

        $owner->givePermissionTo('surveys.view_respondent_identity');

        $this->assertStringContainsString('Farhan Respondent', $service->display($respondent, $fullSurvey, $owner));
        $this->assertStringContainsString('farhan@example.test', $service->display($respondent, $fullSurvey, $owner));
    }

    public function test_super_admin_can_view_full_identity_without_project_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project] = $this->projectFixture();
        $survey = $this->survey($owner, $project, Survey::IDENTITY_FULL);
        $respondent = Respondent::create([
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'name' => 'Super Visible',
            'email' => 'visible@example.test',
        ]);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $display = app(RespondentPrivacyService::class)->display($respondent, $survey, $superAdmin);

        $this->assertStringContainsString('Super Visible', $display);
        $this->assertStringContainsString('visible@example.test', $display);
    }

    public function test_export_row_builder_excludes_identity_by_default_and_gates_identity_columns(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project] = $this->projectFixture();
        [$survey, $response] = $this->responseFixture($owner, $project);
        $builder = app(SurveyResponseExportRowBuilder::class);

        $defaultRow = $builder->build($response, $owner);
        $requestedWithoutPermission = $builder->build($response, $owner, withIdentity: true);

        $this->assertArrayNotHasKey('identity_email', $defaultRow);
        $this->assertArrayNotHasKey('identity_email', $requestedWithoutPermission);
        $this->assertSame('fa****@example.test', $defaultRow['respondent']);
        $this->assertSame('Helpful interface', $defaultRow['feedback']);

        $owner->givePermissionTo('surveys.view_respondent_identity');

        $withIdentity = $builder->build($response, $owner, withIdentity: true);
        $defaultAfterPermission = $builder->build($response, $owner);

        $this->assertSame('farhan@example.test', $withIdentity['identity_email']);
        $this->assertSame('Farhan Respondent', $withIdentity['identity_name']);
        $this->assertArrayNotHasKey('identity_email', $defaultAfterPermission);
    }

    /**
     * @return array{0: User, 1: ResearchProject}
     */
    private function projectFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Privacy Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        return [$owner, $project];
    }

    private function survey(User $owner, ResearchProject $project, string $identityMode): Survey
    {
        return app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Privacy '.$identityMode,
            'identity_mode' => $identityMode,
        ]);
    }

    /**
     * @return array{0: Survey, 1: SurveyResponse}
     */
    private function responseFixture(User $owner, ResearchProject $project): array
    {
        $survey = $this->survey($owner, $project, Survey::IDENTITY_FULL);
        $question = $survey->questions()->create([
            'question_key' => 'feedback',
            'type' => 'long_text',
            'label' => 'Feedback',
            'sort_order' => 1,
        ]);
        $respondent = Respondent::create([
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'name' => 'Farhan Respondent',
            'email' => 'farhan@example.test',
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

        return [$survey, $response->fresh(['survey.project', 'respondent', 'answers'])];
    }
}
