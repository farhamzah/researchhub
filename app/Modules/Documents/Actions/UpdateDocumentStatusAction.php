<?php

namespace App\Modules\Documents\Actions;

use App\Models\Document;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class UpdateDocumentStatusAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Document $document, string $status, ?Request $request = null): Document
    {
        Gate::forUser($user)->authorize('updateStatus', $document);

        if (! in_array($status, config('researchhub_documents.status_values', Document::STATUSES), true)) {
            throw new InvalidArgumentException('Invalid document status.');
        }

        $previousStatus = $document->status;
        $document->forceFill(['status' => $status])->save();

        $this->activityLogger->log(
            'document.status_changed',
            $user,
            $document->project,
            $document,
            [
                'document_id' => $document->getKey(),
                'from' => $previousStatus,
                'to' => $status,
            ],
            $request,
        );

        return $document;
    }
}
