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
use App\Modules\Analysis\Services\AcademicDraftBuilder;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class AnalysisDocxExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_export_safe_docx_analysis_draft(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $result] = $this->analysisResultFixture();

        $response = $this->actingAs($owner)
            ->get(route('admin.analysis.export.docx', ['analysisResult' => $result]))
            ->assertOk();

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $response->headers->get('content-type')
        );
        $this->assertStringContainsString('analysis-draft-'.$result->id, $response->headers->get('content-disposition'));
        $this->assertStringContainsString(now()->format('Ymd').'.docx', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('PK', $response->getContent());

        $text = $this->docxText($response->getContent());

        $this->assertStringContainsString('Draf Akademik', $text);
        $this->assertStringContainsString('Draf BAB IV - Analisis Deskriptif', $text);
        $this->assertStringContainsString('1. Informasi Analisis', $text);
        $this->assertStringContainsString('2. Ringkasan Sumber Data', $text);
        $this->assertStringContainsString('3. Ringkasan Hasil Analisis Deskriptif', $text);
        $this->assertStringContainsString('4. Tabel Hasil Analisis', $text);
        $this->assertStringContainsString('Narasi Akademik Deskriptif', $text);
        $this->assertStringContainsString('6. Catatan Interpretasi', $text);
        $this->assertStringContainsString('7. Checklist Verifikasi Peneliti', $text);
        $this->assertStringContainsString('8. Disclaimer', $text);
        $this->assertStringContainsString('[ ] Data responden telah diverifikasi.', $text);
        $this->assertStringContainsString('[ ] Kesimpulan inferensial belum ditambahkan sebelum uji statistik lanjutan.', $text);
        $this->assertStringContainsString('generated at:', $text);
        $this->assertStringContainsString(AcademicDraftBuilder::DISCLAIMER, $text);
        $this->assertStringContainsString('MyRiset - Draf otomatis deskriptif, wajib diverifikasi.', $this->docxText($response->getContent(), 'word/footer1.xml'));
        $this->assertLessThan(
            mb_strpos($text, '5. Narasi Akademik Deskriptif'),
            mb_strpos($text, '4. Tabel Hasil Analisis')
        );
        $this->assertStringNotContainsString('Farhan Respondent', $text);
        $this->assertStringNotContainsString('farhan@example.test', $text);
        $this->assertStringNotContainsString('SECRET-HIDDEN', $text);
        $this->assertStringNotContainsString('p-value', mb_strtolower($text));
        $this->assertStringNotContainsString('signifikan', mb_strtolower($text));
        $this->assertStringNotContainsString('kausal', mb_strtolower($text));
        $this->assertStringNotContainsString('efektivitas', mb_strtolower($text));

        $log = ActivityLog::where('action', 'analysis.exported')->firstOrFail();
        $this->assertSame('docx', $log->metadata['format']);
        $this->assertSame($result->id, $log->metadata['analysis_result_id']);
        $this->assertArrayNotHasKey('result_payload', $log->metadata);
        $this->assertArrayNotHasKey('tables', $log->metadata);
    }

    public function test_docx_layout_uses_configured_table_style_and_safe_filename(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $project, $result] = $this->analysisResultFixture();

        $response = $this->actingAs($owner)
            ->get(route('admin.analysis.export.docx', ['analysisResult' => $result]))
            ->assertOk();

        $documentXml = $this->docxXml($response->getContent(), 'word/document.xml');

        $this->assertSame('Calibri', config('researchhub_analysis.docx.default_font'));
        $this->assertSame(1440, config('researchhub_analysis.docx.margin_top'));
        $this->assertStringContainsString('ResearchHubAnalysisTable', $documentXml);
        $this->assertStringContainsString('question key', $this->docxText($response->getContent()));
        $this->assertStringContainsString('type', $this->docxText($response->getContent()));
        $this->assertStringNotContainsString('DOCX Export Survey', $response->headers->get('content-disposition'));
    }

    public function test_docx_export_route_is_authenticated_and_policy_protected(): void
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

        $this->get(route('admin.analysis.export.docx', ['analysisResult' => $result]))
            ->assertRedirect('/admin/login');

        $this->actingAs($viewer)
            ->get(route('admin.analysis.export.docx', ['analysisResult' => $result]))
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
            'title' => 'DOCX Export Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'DOCX Export Survey',
            'identity_mode' => Survey::IDENTITY_FULL,
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
            'question_key' => 'ease',
            'answer_value' => 5,
        ]);
        SurveyAnswer::create([
            'survey_response_id' => $response->id,
            'survey_question_id' => $hidden->id,
            'question_key' => 'hidden_token',
            'answer_value' => 'SECRET-HIDDEN',
        ]);

        return [$owner, $project, app(RunSurveyDescriptiveAnalysisAction::class)->handle($owner, $survey)];
    }

    private function docxText(string $content, string $part = 'word/document.xml'): string
    {
        return html_entity_decode(strip_tags($this->docxXml($content, $part)), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function docxXml(string $content, string $part): string
    {
        $path = tempnam(storage_path('framework/cache'), 'docx-test-');
        file_put_contents($path, $content);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));

        $xml = $zip->getFromName($part);
        $zip->close();
        unlink($path);

        $this->assertIsString($xml);

        return $xml;
    }
}
