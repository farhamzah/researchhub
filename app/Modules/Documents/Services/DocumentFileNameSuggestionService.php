<?php

namespace App\Modules\Documents\Services;

use App\Models\Document;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DocumentFileNameSuggestionService
{
    public function suggest(Document $document, ?Carbon $date = null, string $extension = 'docx'): string
    {
        $date ??= today();

        return collect([
            $this->projectSlug($document),
            $this->documentTypeSlug($document),
            $this->versionSlug($document),
            $date->format('Y-m-d'),
        ])
            ->filter()
            ->join('_').'.'.ltrim($extension, '.');
    }

    private function projectSlug(Document $document): string
    {
        return Str::slug($document->project?->slug ?: $document->project?->title ?: 'project');
    }

    private function documentTypeSlug(Document $document): string
    {
        return match ($document->document_type) {
            Document::TYPE_CHAPTER_1 => 'bab-i',
            Document::TYPE_CHAPTER_2 => 'bab-ii',
            Document::TYPE_CHAPTER_3 => 'bab-iii',
            Document::TYPE_VALIDATION_REPORT => 'validation-report',
            Document::TYPE_ANALYSIS_REPORT => 'analysis-report',
            Document::TYPE_SUPERVISION_LOG => 'supervision-log',
            Document::TYPE_JOURNAL_ARTICLE => 'journal-article',
            Document::TYPE_PUBLICATION_DRAFT => 'publication-draft',
            default => Str::slug($document->document_type ?: $document->category?->slug ?: $document->title ?: 'document'),
        };
    }

    private function versionSlug(Document $document): string
    {
        $version = filled($document->version_label)
            ? (string) $document->version_label
            : 'v'.str_pad((string) (int) ($document->version_number ?: 1), 2, '0', STR_PAD_LEFT);

        return Str::slug($version);
    }
}
