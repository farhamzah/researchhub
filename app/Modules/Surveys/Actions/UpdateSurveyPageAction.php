<?php

namespace App\Modules\Surveys\Actions;

use App\Models\SurveyPage;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UpdateSurveyPageAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, SurveyPage $page, array $attributes, ?Request $request = null): SurveyPage
    {
        Gate::forUser($user)->authorize('update', $page->survey);

        $page->fill([
            'title' => $attributes['title'] ?? null,
            'description' => $attributes['description'] ?? null,
            'sort_order' => (int) ($attributes['sort_order'] ?? $page->sort_order),
            'settings' => $attributes['settings'] ?? $page->settings,
        ])->save();

        $this->activityLogger->log('survey.page_updated', $user, $page->survey->project, $page, [
            'survey_id' => $page->survey_id,
            'page_id' => $page->getKey(),
        ], $request);

        return $page->fresh();
    }
}
