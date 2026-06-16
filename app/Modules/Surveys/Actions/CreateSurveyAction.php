<?php

namespace App\Modules\Surveys\Actions;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateSurveyAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, ResearchProject $project, array $attributes, ?Request $request = null): Survey
    {
        Gate::forUser($user)->authorize('update', $project);

        $identityMode = (string) ($attributes['identity_mode'] ?? Survey::IDENTITY_HIDDEN);

        if (! in_array($identityMode, Survey::IDENTITY_MODES, true)) {
            throw ValidationException::withMessages(['identity_mode' => 'Invalid survey identity mode.']);
        }

        validator($attributes, [
            'instrument_type' => ['nullable', 'string', Rule::in(Survey::INSTRUMENT_TYPES)],
            'parent_survey_id' => ['nullable', 'string', 'exists:surveys,id'],
            'analysis_group_key' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $survey = Survey::create([
            'project_id' => $project->getKey(),
            'created_by' => $user->getKey(),
            'title' => (string) $attributes['title'],
            'slug' => $this->uniqueSlug((string) ($attributes['slug'] ?? $attributes['title'])),
            'description' => $attributes['description'] ?? null,
            'schema' => $attributes['schema'] ?? null,
            'identity_mode' => $identityMode,
            'instrument_type' => $attributes['instrument_type'] ?? null,
            'parent_survey_id' => $attributes['parent_survey_id'] ?? null,
            'analysis_group_key' => $attributes['analysis_group_key'] ?? null,
            'status' => Survey::STATUS_DRAFT,
            'is_public' => false,
        ]);

        $this->activityLogger->log('survey.created', $user, $project, $survey, [
            'survey_id' => $survey->getKey(),
            'status' => $survey->status,
            'identity_mode' => $survey->identity_mode,
        ], $request);

        return $survey;
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'survey';
        $slug = $base;
        $counter = 2;

        while (Survey::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
