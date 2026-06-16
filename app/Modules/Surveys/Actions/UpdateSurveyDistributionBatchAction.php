<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyDistributionBatch;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateSurveyDistributionBatchAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Survey $survey, string $audienceType, array $attributes, ?Request $request = null): SurveyDistributionBatch
    {
        Gate::forUser($user)->authorize('update', $survey);

        validator(['audience_type' => $audienceType] + $attributes, [
            'audience_type' => ['required', 'string', Rule::in(SurveyDistributionBatch::AUDIENCES)],
            'title' => ['nullable', 'string', 'max:255'],
            'message_subject' => ['nullable', 'string', 'max:255'],
            'message_body' => ['nullable', 'string', 'max:10000'],
            'deadline' => ['nullable', 'date'],
            'status' => ['required', 'string', Rule::in(SurveyDistributionBatch::STATUSES)],
            'notes' => ['nullable', 'string', 'max:10000'],
        ])->validate();

        $batch = SurveyDistributionBatch::query()->updateOrCreate(
            [
                'survey_id' => $survey->getKey(),
                'audience_type' => $audienceType,
            ],
            [
                'project_id' => $survey->project_id,
                'title' => $attributes['title'] ?? (SurveyDistributionBatch::AUDIENCE_LABELS[$audienceType] ?? 'Distribution Batch'),
                'message_subject' => $attributes['message_subject'] ?? null,
                'message_body' => $attributes['message_body'] ?? null,
                'deadline' => $attributes['deadline'] ?? null,
                'status' => $attributes['status'],
                'created_by' => $user->getKey(),
                'notes' => $attributes['notes'] ?? null,
            ],
        );

        $this->activityLogger->log('survey_distribution_batch.updated', $user, $survey->project, $batch, [
            'survey_id' => $survey->getKey(),
            'audience_type' => $audienceType,
            'status' => $batch->status,
            'deadline' => $batch->deadline?->toDateString(),
        ], $request);

        return $batch;
    }
}
