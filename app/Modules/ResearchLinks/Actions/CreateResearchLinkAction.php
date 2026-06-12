<?php

namespace App\Modules\ResearchLinks\Actions;

use App\Models\ResearchLink;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\ResearchLinks\Services\ResearchLinkUrlSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateResearchLinkAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ResearchLinkUrlSafetyService $urlSafety,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes, ?Request $request = null): ResearchLink
    {
        $project = $this->projectFor($user, $attributes['research_project_id'] ?? null);
        $category = $this->category($attributes['category'] ?? ResearchLink::CATEGORY_OTHER);

        $researchLink = ResearchLink::create([
            'research_project_id' => $project?->getKey(),
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
            'title' => (string) $attributes['title'],
            'url' => $this->urlSafety->assertSafe((string) $attributes['url']),
            'description' => $attributes['description'] ?? null,
            'category' => $category,
            'thumbnail_url' => $this->urlSafety->assertSafe($attributes['thumbnail_url'] ?? null, 'thumbnail_url'),
            'favicon_url' => $this->urlSafety->assertSafe($attributes['favicon_url'] ?? null, 'favicon_url'),
            'is_pinned' => (bool) ($attributes['is_pinned'] ?? false),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            'metadata' => $attributes['metadata'] ?? null,
        ]);

        $this->activityLogger->log('research_link.created', $user, $project, $researchLink, $this->metadata($researchLink), $request);

        return $researchLink;
    }

    private function projectFor(User $user, mixed $projectId): ?ResearchProject
    {
        if (blank($projectId)) {
            return null;
        }

        $project = ResearchProject::query()->find($projectId);

        if (! $project || Gate::forUser($user)->denies('update', $project)) {
            throw ValidationException::withMessages([
                'research_project_id' => 'Select a research project you are allowed to manage.',
            ]);
        }

        return $project;
    }

    private function category(mixed $category): string
    {
        $category = (string) $category;

        if (! in_array($category, ResearchLink::CATEGORIES, true)) {
            throw ValidationException::withMessages([
                'category' => 'Select a valid research link category.',
            ]);
        }

        return $category;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(ResearchLink $researchLink): array
    {
        return [
            'research_link_id' => $researchLink->getKey(),
            'research_project_id' => $researchLink->research_project_id,
            'category' => $researchLink->category,
            'is_pinned' => $researchLink->is_pinned,
            'is_active' => $researchLink->is_active,
            'host' => $this->urlSafety->host($researchLink->url),
        ];
    }
}
