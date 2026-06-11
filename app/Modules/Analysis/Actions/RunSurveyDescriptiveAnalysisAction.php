<?php

namespace App\Modules\Analysis\Actions;

use App\Models\AnalysisJob;
use App\Models\AnalysisResult;
use App\Models\Survey;
use App\Models\User;
use App\Modules\Analysis\Services\AcademicNarrativeGenerator;
use App\Modules\Analysis\Services\SurveyDescriptiveAnalysisService;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class RunSurveyDescriptiveAnalysisAction
{
    public function __construct(
        private readonly SurveyDescriptiveAnalysisService $analysisService,
        private readonly AcademicNarrativeGenerator $narrativeGenerator,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, Survey $survey, ?Request $request = null): AnalysisResult
    {
        $survey->loadMissing('project');

        Gate::forUser($user)->authorize('runAnalysis', $survey);

        $job = AnalysisJob::create([
            'project_id' => $survey->project_id,
            'survey_id' => $survey->getKey(),
            'created_by' => $user->getKey(),
            'type' => AnalysisJob::TYPE_SURVEY_DESCRIPTIVE,
            'status' => AnalysisJob::STATUS_RUNNING,
            'input_config' => [
                'response_status' => 'submitted_only',
                'hidden_questions' => 'omitted',
            ],
            'started_at' => now(),
        ]);

        $this->activityLogger->log('analysis.started', $user, $survey->project, $job, [
            'analysis_job_id' => $job->getKey(),
            'survey_id' => $survey->getKey(),
            'type' => $job->type,
        ], $request);

        try {
            $analysis = $this->analysisService->analyze($survey);
            $narrative = $this->narrativeGenerator->generate($analysis);

            $result = DB::transaction(function () use ($job, $survey, $analysis, $narrative): AnalysisResult {
                $result = AnalysisResult::create([
                    'analysis_job_id' => $job->getKey(),
                    'project_id' => $survey->project_id,
                    'survey_id' => $survey->getKey(),
                    'type' => AnalysisJob::TYPE_SURVEY_DESCRIPTIVE,
                    'title' => 'Descriptive Analysis - '.$survey->title,
                    'summary' => $analysis['summary'],
                    'result_payload' => [
                        'survey' => $analysis['survey'],
                        'questions' => $analysis['questions'],
                    ],
                ]);

                foreach ($analysis['tables'] as $table) {
                    $result->tables()->create([
                        'title' => $table['title'],
                        'table_key' => $table['table_key'],
                        'columns' => $table['columns'],
                        'rows' => $table['rows'],
                    ]);
                }

                $result->narratives()->create([
                    'section' => 'descriptive_summary',
                    'language' => 'id',
                    'narrative' => $narrative,
                ]);

                $job->update([
                    'status' => AnalysisJob::STATUS_COMPLETED,
                    'finished_at' => now(),
                ]);

                return $result->load(['job', 'project', 'survey', 'tables', 'narratives']);
            });

            $this->activityLogger->log('analysis.completed', $user, $survey->project, $result, [
                'analysis_job_id' => $job->getKey(),
                'survey_id' => $survey->getKey(),
                'type' => $job->type,
                'response_count' => $analysis['summary']['submitted_count'],
            ], $request);

            return $result;
        } catch (Throwable $exception) {
            $job->update([
                'status' => AnalysisJob::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            $this->activityLogger->log('analysis.failed', $user, $survey->project, $job, [
                'analysis_job_id' => $job->getKey(),
                'survey_id' => $survey->getKey(),
                'type' => $job->type,
            ], $request);

            throw $exception;
        }
    }
}
