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

class CreateDocumentAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        User $user,
        ResearchProject $project,
        DocumentCategory $category,
        array $attributes,
        ?Request $request = null,
    ): Document {
        Gate::forUser($user)->authorize('create', [Document::class, $project]);

        $status = (string) ($attributes['status'] ?? Document::STATUS_DRAFT);
        $visibility = (string) ($attributes['visibility'] ?? Document::VISIBILITY_PRIVATE);

        if (! in_array($status, config('researchhub_documents.status_values', Document::STATUSES), true)) {
            throw new InvalidArgumentException('Invalid document status.');
        }

        if (! in_array($visibility, config('researchhub_documents.visibility_values', Document::VISIBILITIES), true)) {
            throw new InvalidArgumentException('Invalid document visibility.');
        }

        $document = Document::create([
            'project_id' => $project->getKey(),
            'category_id' => $category->getKey(),
            'owner_id' => $user->getKey(),
            'title' => (string) $attributes['title'],
            'slug' => $this->uniqueSlug($project, (string) ($attributes['slug'] ?? $attributes['title'])),
            'description' => $attributes['description'] ?? null,
            'status' => $status,
            'visibility' => $visibility,
            'tags' => $attributes['tags'] ?? null,
        ]);

        $this->activityLogger->log(
            'document.created',
            $user,
            $project,
            $document,
            [
                'document_id' => $document->getKey(),
                'category' => $category->slug,
                'status' => $document->status,
                'visibility' => $document->visibility,
            ],
            $request,
        );

        return $document;
    }

    private function uniqueSlug(ResearchProject $project, string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'document';
        $slug = $baseSlug;
        $suffix = 2;

        while (Document::query()->where('project_id', $project->getKey())->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
