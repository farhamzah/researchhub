<?php

namespace App\Modules\Documents\Actions;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateDocumentAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        User $user,
        Document $document,
        ResearchProject $project,
        DocumentCategory $category,
        array $attributes,
        ?Request $request = null,
    ): Document {
        Gate::forUser($user)->authorize('update', $document);

        if ($document->project_id !== $project->getKey()) {
            Gate::forUser($user)->authorize('create', [Document::class, $project]);
        }

        $status = (string) ($attributes['status'] ?? $document->status);
        $visibility = (string) ($attributes['visibility'] ?? $document->visibility);

        if (! in_array($status, config('researchhub_documents.status_values', Document::STATUSES), true)) {
            throw new InvalidArgumentException('Invalid document status.');
        }

        if (! in_array($visibility, config('researchhub_documents.visibility_values', Document::VISIBILITIES), true)) {
            throw new InvalidArgumentException('Invalid document visibility.');
        }

        $previousProjectId = $document->project_id;
        $previousStatus = $document->status;
        $previousVisibility = $document->visibility;
        $title = (string) $attributes['title'];

        $document->forceFill([
            'project_id' => $project->getKey(),
            'category_id' => $category->getKey(),
            'title' => $title,
            'slug' => $this->uniqueSlug($project, $title, $document),
            'description' => $attributes['description'] ?? null,
            'status' => $status,
            'visibility' => $visibility,
            'tags' => $attributes['tags'] ?? null,
        ])->save();

        $this->activityLogger->log(
            'document.updated',
            $user,
            $project,
            $document,
            [
                'document_id' => $document->getKey(),
                'category' => $category->slug,
                'project_changed' => $previousProjectId !== $project->getKey(),
                'status_changed' => $previousStatus !== $document->status,
                'visibility_changed' => $previousVisibility !== $document->visibility,
            ],
            $request,
        );

        return $document;
    }

    private function uniqueSlug(ResearchProject $project, string $title, Document $document): string
    {
        $baseSlug = Str::slug($title) ?: 'document';
        $slug = $baseSlug;
        $suffix = 2;

        while (Document::query()
            ->where('project_id', $project->getKey())
            ->where('slug', $slug)
            ->whereKeyNot($document->getKey())
            ->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
