<?php

namespace App\Modules\Documents\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Modules\Documents\DTOs\DocumentUploadData;
use RuntimeException;

class DocumentStorageService
{
    public function store(Document $document, User $user, DocumentUploadData $uploadData): DocumentUploadData
    {
        $uploadData = $uploadData->withSanitizedNames();

        if (in_array($uploadData->storageStatus, [
            DocumentVersion::STORAGE_STATUS_FAKE,
            DocumentVersion::STORAGE_STATUS_EXTERNAL,
            DocumentVersion::STORAGE_STATUS_STORED,
        ], true)) {
            return $uploadData;
        }

        throw new RuntimeException('Live Google Drive upload is not configured for this document storage operation.');
    }
}
