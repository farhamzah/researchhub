<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyPage;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CreateSurveyPageAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Survey $survey, array $attributes, ?Request $request = null): SurveyPage
    {
        Gate::forUser($user)->authorize('update', $survey);

        $page = $survey->pages()->create([
            'title' => $attributes['title'] ?? null,
            'description' => $attributes['description'] ?? null,
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            'settings' => $attributes['settings'] ?? null,
        ]);

        $this->activityLogger->log('survey.page_created', $user, $survey->project, $page, [
            'survey_id' => $survey->getKey(),
            'page_id' => $page->getKey(),
        ], $request);

        return $page;
    }
}
