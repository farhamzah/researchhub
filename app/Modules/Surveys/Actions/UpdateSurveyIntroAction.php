<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UpdateSurveyIntroAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Survey $survey, array $attributes, ?Request $request = null): Survey
    {
        Gate::forUser($user)->authorize('update', $survey);

        $survey->fill([
            'intro_title' => $attributes['intro_title'] ?? null,
            'intro_text' => $attributes['intro_text'] ?? null,
            'estimated_duration' => $attributes['estimated_duration'] ?? null,
            'privacy_statement' => $attributes['privacy_statement'] ?? null,
            'respondent_instruction' => $attributes['respondent_instruction'] ?? null,
            'consent_text' => $attributes['consent_text'] ?? null,
            'require_consent_before_start' => (bool) ($attributes['require_consent_before_start'] ?? false),
        ])->save();

        $this->activityLogger->log('survey.intro_updated', $user, $survey->project, $survey, [
            'survey_id' => $survey->getKey(),
            'requires_consent' => $survey->require_consent_before_start,
        ], $request);

        return $survey;
    }
}
