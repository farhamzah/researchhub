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
        $documentType = $attributes['document_type'] ?? $document->document_type;

        if (! in_array($status, config('researchhub_documents.status_values', Document::STATUSES), true)) {
            throw new InvalidArgumentException('Invalid document status.');
        }

        if (! in_array($visibility, config('researchhub_documents.visibility_values', Document::VISIBILITIES), true)) {
            throw new InvalidArgumentException('Invalid document visibility.');
        }

        if (filled($documentType) && ! in_array($documentType, config('researchhub_documents.type_values', Document::TYPES), true)) {
            throw new InvalidArgumentException('Invalid document type.');
        }

        $previousProjectId = $document->project_id;
        $previousStatus = $document->status;
        $previousVisibility = $document->visibility;
        $previousVersionLabel = $document->version_label;
        $previousVersionNumber = $document->version_number;
        $title = (string) $attributes['title'];

        $document->forceFill([
            'project_id' => $project->getKey(),
            'category_id' => $category->getKey(),
            'title' => $title,
            'slug' => $this->uniqueSlug($project, $title, $document),
            'description' => $attributes['description'] ?? null,
            'status' => $status,
            'visibility' => $visibility,
            'document_type' => filled($documentType) ? (string) $documentType : null,
            'version_label' => $attributes['version_label'] ?? null,
            'version_number' => (int) ($attributes['version_number'] ?? 1),
            'is_current' => (bool) ($attributes['is_current'] ?? true),
            'reviewer_name' => $attributes['reviewer_name'] ?? null,
            'reviewed_at' => $attributes['reviewed_at'] ?? null,
            'revision_due_date' => $attributes['revision_due_date'] ?? null,
            'next_action' => $attributes['next_action'] ?? null,
            'revision_summary' => $attributes['revision_summary'] ?? null,
            'tags' => $attributes['tags'] ?? null,
        ])->save();

        if ($previousStatus !== $document->status) {
            $this->activityLogger->log(
                'document.status_updated',
                $user,
                $project,
                $document,
                [
                    'document_id' => $document->getKey(),
                    'research_project_id' => $project->getKey(),
                    'old_status' => $previousStatus,
                    'new_status' => $document->status,
                    'version_label' => $document->version_label,
                ],
                $request,
            );
        }

        if ($previousVersionLabel !== $document->version_label || $previousVersionNumber !== $document->version_number) {
            $this->activityLogger->log(
                'document.version_updated',
                $user,
                $project,
                $document,
                [
                    'document_id' => $document->getKey(),
                    'research_project_id' => $project->getKey(),
                    'version_label' => $document->version_label,
                    'version_number' => $document->version_number,
                ],
                $request,
            );
        }

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
                'document_type' => $document->document_type,
                'version_label' => $document->version_label,
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
