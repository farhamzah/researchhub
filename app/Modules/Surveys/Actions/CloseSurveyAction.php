<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CloseSurveyAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Survey $survey, ?Request $request = null): Survey
    {
        Gate::forUser($user)->authorize('close', $survey);

        if (! $survey->canTransitionTo(Survey::STATUS_CLOSED)) {
            throw ValidationException::withMessages(['status' => 'This survey cannot be closed from its current status.']);
        }

        $survey->forceFill([
            'status' => Survey::STATUS_CLOSED,
            'is_public' => false,
            'closed_at' => now(),
        ])->save();

        $this->activityLogger->log('survey.closed', $user, $survey->project, $survey, [
            'survey_id' => $survey->getKey(),
            'closed_at' => $survey->closed_at?->toISOString(),
        ], $request);

        return $survey->fresh();
    }
}
