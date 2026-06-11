<?php

namespace App\Modules\Analysis\Services;

use App\Models\AnalysisResult;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class AnalysisExportService
{
    public function __construct(
        private readonly AnalysisCsvExporter $csvExporter,
        private readonly AnalysisMarkdownExporter $markdownExporter,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * @return array{content: string, filename: string, content_type: string}
     */
    public function export(AnalysisResult $result, User $user, string $format, ?Request $request = null): array
    {
        $result->loadMissing(['job', 'project', 'survey', 'tables', 'narratives']);

        Gate::forUser($user)->authorize('export', $result);

        $export = match ($format) {
            'csv' => [
                'content' => $this->csvExporter->export($result),
                'filename' => $this->csvExporter->filename($result),
                'content_type' => 'text/csv; charset=UTF-8',
            ],
            'markdown' => [
                'content' => $this->markdownExporter->export($result),
                'filename' => $this->markdownExporter->filename($result),
                'content_type' => 'text/markdown; charset=UTF-8',
            ],
            default => throw new InvalidArgumentException('Unsupported analysis export format.'),
        };

        $this->activityLogger->log('analysis.exported', $user, $result->project, $result, [
            'analysis_result_id' => $result->getKey(),
            'analysis_job_id' => $result->analysis_job_id,
            'survey_id' => $result->survey_id,
            'format' => $format,
        ], $request);

        return $export;
    }
}
