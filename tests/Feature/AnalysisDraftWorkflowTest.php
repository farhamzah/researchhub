<?php

namespace Tests\Feature;

use App\Models\AnalysisResult;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Analysis\Actions\RunSurveyDescriptiveAnalysisAction;
use App\Modules\Analysis\Services\AcademicDraftBuilder;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisDraftWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_markdown_export_contains_academic_draft_sections_and_disclaimer(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $result] = $this->analysisResultFixture();

        $response = $this->actingAs($owner)
            ->get(route('admin.analysis.export.markdown', ['analysisResult' => $result]))
            ->assertOk();

        $markdown = $response->getContent();

        $this->assertStringContainsString('text/markdown', $response->headers->get('content-type'));
        $this->assertStringContainsString('# Draf Akademik', $markdown);
        $this->assertStringContainsString('## Analysis Metadata', $markdown);
        $this->assertStringContainsString('## Judul Analisis', $markdown);
        $this->assertStringContainsString('## Ringkasan Data', $markdown);
        $this->assertStringContainsString('## Deskripsi Responden tanpa identitas', $markdown);
        $this->assertStringContainsString('## Ringkasan Hasil Per Pertanyaan/Indikator', $markdown);
        $this->assertStringContainsString('## Narasi Interpretasi Deskriptif', $markdown);
        $this->assertStringContainsString('## Catatan Verifikasi', $markdown);
        $this->assertStringContainsString(AcademicDraftBuilder::DISCLAIMER, $markdown);
        $this->assertStringContainsString('| question_key | type | answered_count |', $markdown);
        $this->assertStringNotContainsString('signifikan', mb_strtolower($markdown));
        $this->assertStringNotContainsString('p-value', mb_strtolower($markdown));
        $this->assertStringNotContainsString('kausal', mb_strtolower($markdown));
        $this->assertStringNotContainsString('efektivitas', mb_strtolower($markdown));
        $this->assertStringNotContainsString('SECRET-HIDDEN', $markdown);
    }

    public function test_analysis_show_page_has_export_and_copy_ready_workflow(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $result] = $this->analysisResultFixture();

        $this->actingAs($owner)
            ->get(route('admin.analysis.results.show', ['analysisResult' => $result]))
            ->assertOk()
            ->assertSee('Export CSV')
            ->assertSee('Export Markdown')
            ->assertSee('Export DOCX Draft')
            ->assertSee('draf akademik deskriptif otomatis')
            ->assertSee('Copy-ready narrative block')
            ->assertSee(AcademicDraftBuilder::DISCLAIMER);
    }

    /**
     * @return array{0: User, 1: AnalysisResult}
     */
    private function analysisResultFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Draft Workflow Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Draft Workflow Survey',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);
        $question = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'ease',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Kemudahan penggunaan',
            'settings' => ['scale' => [1, 2, 3, 4, 5]],
        ]);
        $hidden = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'hidden_token',
            'type' => SurveyQuestion::TYPE_HIDDEN,
            'label' => 'Hidden token',
        ]);
        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
        SurveyAnswer::create([
            'survey_response_id' => $response->id,
            'survey_question_id' => $question->id,
            'question_key' => 'ease',
            'answer_value' => 5,
        ]);
        SurveyAnswer::create([
            'survey_response_id' => $response->id,
            'survey_question_id' => $hidden->id,
            'question_key' => 'hidden_token',
            'answer_value' => 'SECRET-HIDDEN',
        ]);

        return [$owner, app(RunSurveyDescriptiveAnalysisAction::class)->handle($owner, $survey)];
    }
}
