<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateSurveyAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Survey $survey, array $attributes, ?Request $request = null): Survey
    {
        Gate::forUser($user)->authorize('update', $survey);

        if (isset($attributes['identity_mode']) && ! in_array($attributes['identity_mode'], Survey::IDENTITY_MODES, true)) {
            throw ValidationException::withMessages(['identity_mode' => 'Invalid survey identity mode.']);
        }

        $survey->fill(collect($attributes)->only([
            'title',
            'description',
            'schema',
            'identity_mode',
        ])->all());
        $survey->save();

        $this->activityLogger->log('survey.updated', $user, $survey->project, $survey, [
            'survey_id' => $survey->getKey(),
            'status' => $survey->status,
            'identity_mode' => $survey->identity_mode,
        ], $request);

        return $survey->fresh();
    }
}
