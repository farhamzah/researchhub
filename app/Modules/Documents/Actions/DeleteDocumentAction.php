<?php

namespace App\Modules\Documents\Actions;

use App\Models\Document;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeleteDocumentAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Document $document, ?Request $request = null): void
    {
        Gate::forUser($user)->authorize('delete', $document);

        $document->delete();

        $this->activityLogger->log(
            'document.deleted',
            $user,
            $document->project,
            $document,
            ['document_id' => $document->getKey()],
            $request,
        );
    }
}
