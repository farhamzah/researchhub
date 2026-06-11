<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ProjectMember;
use App\Models\User;
use App\Modules\Analysis\Services\AcademicDraftBuilder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DocxTextExtractor;
use Tests\Support\ReportExportFixture;
use Tests\Support\ReportExportFixtureBuilder;
use Tests\TestCase;

class ReportExportQaTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_exports_are_safe_complete_and_parseable(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixture = app(ReportExportFixtureBuilder::class)->create();
        $docxExtractor = new DocxTextExtractor;

        $csvResponse = $this->actingAs($fixture->owner)
            ->get(route('admin.analysis.export.csv', ['analysisResult' => $fixture->result]))
            ->assertOk();
        $markdownResponse = $this->actingAs($fixture->owner)
            ->get(route('admin.analysis.export.markdown', ['analysisResult' => $fixture->result]))
            ->assertOk();
        $docxResponse = $this->actingAs($fixture->owner)
            ->get(route('admin.analysis.export.docx', ['analysisResult' => $fixture->result]))
            ->assertOk();

        $csv = $csvResponse->getContent();
        $markdown = $markdownResponse->getContent();
        $docxText = $docxExtractor->text($docxResponse->getContent(), ['word/document.xml', 'word/footer1.xml']);

        $this->assertCsvExport($csv, $fixture);
        $this->assertMarkdownExport($markdown, $fixture);
        $this->assertDocxExport($docxText, $fixture);

        foreach ([$csv, $markdown, $docxText] as $exportText) {
            $this->assertExportDoesNotLeakUnsafeFixtureData($exportText, $fixture);
        }

        $logs = ActivityLog::where('action', 'analysis.exported')->latest()->take(3)->get();
        $this->assertCount(3, $logs);
        $this->assertEqualsCanonicalizing(['csv', 'markdown', 'docx'], $logs->pluck('metadata.format')->all());

        foreach ($logs as $log) {
            $this->assertSame($fixture->result->id, $log->metadata['analysis_result_id']);
            $this->assertSame($fixture->result->analysis_job_id, $log->metadata['analysis_job_id']);
            $this->assertSame($fixture->result->survey_id, $log->metadata['survey_id']);
            $metadata = json_encode($log->metadata, JSON_THROW_ON_ERROR);
            $this->assertExportDoesNotLeakUnsafeFixtureData($metadata, $fixture);
            $this->assertArrayNotHasKey('result_payload', $log->metadata);
            $this->assertArrayNotHasKey('tables', $log->metadata);
        }
    }

    public function test_report_export_filenames_are_safe_and_stable(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixture = app(ReportExportFixtureBuilder::class)->create();

        $responses = [
            'csv' => $this->actingAs($fixture->owner)->get(route('admin.analysis.export.csv', ['analysisResult' => $fixture->result]))->assertOk(),
            'md' => $this->actingAs($fixture->owner)->get(route('admin.analysis.export.markdown', ['analysisResult' => $fixture->result]))->assertOk(),
            'docx' => $this->actingAs($fixture->owner)->get(route('admin.analysis.export.docx', ['analysisResult' => $fixture->result]))->assertOk(),
        ];

        foreach ($responses as $extension => $response) {
            $filename = $this->filenameFromDisposition($response->headers->get('content-disposition'));

            $this->assertStringContainsString($fixture->result->id, $filename);
            $this->assertStringContainsString(now()->format('Ymd'), $filename);
            $this->assertStringEndsWith('.'.$extension, $filename);
            $this->assertDoesNotMatchRegularExpression('/[\s\/\\\\:]/', $filename);
            $this->assertStringNotContainsString('Safe Visible Export Survey', $filename);
            $this->assertStringNotContainsString('Draft', $filename);
        }
    }

    public function test_report_export_routes_remain_authenticated_and_policy_protected(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixture = app(ReportExportFixtureBuilder::class)->create();
        $viewer = User::factory()->create();

        ProjectMember::create([
            'project_id' => $fixture->project->id,
            'user_id' => $viewer->id,
            'role' => ProjectMember::ROLE_VIEWER,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);

        $routes = collect(['csv', 'markdown', 'docx'])
            ->map(fn (string $format): string => route('admin.analysis.export.'.$format, ['analysisResult' => $fixture->result]));

        foreach ($routes as $route) {
            $this->get($route)->assertRedirect('/admin/login');
        }

        foreach ($routes as $route) {
            $this->actingAs($viewer)->get($route)->assertForbidden();
        }
    }

    private function assertCsvExport(string $csv, ReportExportFixture $fixture): void
    {
        $rows = $this->csvRows($csv);

        $this->assertGreaterThanOrEqual(2, count($rows));
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
        ], $rows[0]);
        $this->assertStringContainsString('question_descriptive_summary', $csv);
        $this->assertStringContainsString(ReportExportFixtureBuilder::SAFE_TABLE_TITLE, $csv);
        $this->assertStringContainsString(ReportExportFixtureBuilder::SAFE_TABLE_ROW, $csv);
        $this->assertStringContainsString('safe_visible_metric', $csv);
    }

    private function assertMarkdownExport(string $markdown, ReportExportFixture $fixture): void
    {
        $this->assertStringContainsString('# Draf Akademik', $markdown);
        $this->assertStringContainsString('## Analysis Metadata', $markdown);
        $this->assertStringContainsString('## Disclaimer', $markdown);
        $this->assertStringContainsString(AcademicDraftBuilder::DISCLAIMER, $markdown);
        $this->assertStringContainsString(ReportExportFixtureBuilder::SAFE_SURVEY_TITLE, $markdown);
        $this->assertStringContainsString(ReportExportFixtureBuilder::SAFE_NARRATIVE, $markdown);
        $this->assertStringContainsString(ReportExportFixtureBuilder::SAFE_TABLE_TITLE, $markdown);
        $this->assertStringContainsString(ReportExportFixtureBuilder::SAFE_TABLE_ROW, $markdown);
    }

    private function assertDocxExport(string $docxText, ReportExportFixture $fixture): void
    {
        foreach ([
            'Informasi Analisis',
            'Ringkasan Sumber Data',
            'Ringkasan Hasil Analisis Deskriptif',
            'Tabel Hasil Analisis',
            'Narasi Akademik Deskriptif',
            'Catatan Interpretasi',
            'Checklist Verifikasi Peneliti',
            'Disclaimer',
        ] as $section) {
            $this->assertStringContainsString($section, $docxText);
        }

        $this->assertStringContainsString(AcademicDraftBuilder::DISCLAIMER, $docxText);
        $this->assertStringContainsString(ReportExportFixtureBuilder::SAFE_SURVEY_TITLE, $docxText);
        $this->assertStringContainsString(ReportExportFixtureBuilder::SAFE_NARRATIVE, $docxText);
        $this->assertStringContainsString(ReportExportFixtureBuilder::SAFE_TABLE_TITLE, $docxText);
        $this->assertStringContainsString(ReportExportFixtureBuilder::SAFE_TABLE_ROW, $docxText);
    }

    private function assertExportDoesNotLeakUnsafeFixtureData(string $content, ReportExportFixture $fixture): void
    {
        foreach ([...$fixture->sensitiveStrings, $fixture->rawPayloadMarker] as $unsafeString) {
            $this->assertStringNotContainsString($unsafeString, $content);
        }

        $lowerContent = mb_strtolower($content);
        foreach ($fixture->forbiddenTerms as $forbiddenTerm) {
            $this->assertStringNotContainsString(mb_strtolower($forbiddenTerm), $lowerContent);
        }
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

    private function filenameFromDisposition(?string $disposition): string
    {
        $this->assertIsString($disposition);
        $matched = preg_match('/filename="([^"]+)"/', $disposition, $matches);
        $this->assertSame(1, $matched);

        return $matches[1];
    }
}
