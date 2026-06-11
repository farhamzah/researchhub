<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PublishSurveyAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Survey $survey, ?Request $request = null): Survey
    {
        Gate::forUser($user)->authorize('publish', $survey);

        if (! $survey->canTransitionTo(Survey::STATUS_PUBLISHED)) {
            throw ValidationException::withMessages(['status' => 'This survey cannot be published from its current status.']);
        }

        $survey->forceFill([
            'status' => Survey::STATUS_PUBLISHED,
            'is_public' => true,
            'published_at' => now(),
            'closed_at' => null,
        ])->save();

        $this->activityLogger->log('survey.published', $user, $survey->project, $survey, [
            'survey_id' => $survey->getKey(),
            'is_public' => $survey->is_public,
            'published_at' => $survey->published_at?->toISOString(),
        ], $request);

        return $survey->fresh();
    }
}
