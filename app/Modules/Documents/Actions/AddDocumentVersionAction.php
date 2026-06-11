<?php

namespace App\Modules\Documents\Actions;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Documents\DTOs\DocumentUploadData;
use App\Modules\Documents\Services\DocumentStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class AddDocumentVersionAction
{
    public function __construct(
        private readonly DocumentStorageService $storageService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, Document $document, DocumentUploadData $uploadData, ?Request $request = null): DocumentVersion
    {
        Gate::forUser($user)->authorize('addVersion', $document);

        try {
            $storedUpload = $this->storageService->store($document, $user, $uploadData);

            $version = DB::transaction(function () use ($user, $document, $storedUpload): DocumentVersion {
                $versionNumber = ((int) $document->versions()->max('version_number')) + 1;

                $version = DocumentVersion::create([
                    'document_id' => $document->getKey(),
                    'version_number' => $versionNumber,
                    'uploaded_by' => $user->getKey(),
                    'drive_file_id' => $storedUpload->driveFileId,
                    'drive_folder_id' => $storedUpload->driveFolderId,
                    'file_name' => $storedUpload->fileName,
                    'original_file_name' => $storedUpload->originalFileName,
                    'mime_type' => $storedUpload->mimeType,
                    'file_extension' => $storedUpload->fileExtension,
                    'file_size' => $storedUpload->fileSize,
                    'checksum' => $storedUpload->checksum,
                    'web_view_link' => $storedUpload->webViewLink,
                    'web_download_link' => $storedUpload->webDownloadLink,
                    'storage_status' => $storedUpload->storageStatus,
                    'notes' => $storedUpload->notes,
                ]);

                $document->forceFill(['current_version_id' => $version->getKey()])->save();

                return $version;
            });

            $this->activityLogger->log(
                'document.version_added',
                $user,
                $document->project,
                $version,
                [
                    'document_id' => $document->getKey(),
                    'version_number' => $version->version_number,
                    'storage_status' => $version->storage_status,
                    'file_extension' => $version->file_extension,
                    'file_size' => $version->file_size,
                ],
                $request,
            );

            return $version;
        } catch (Throwable $exception) {
            $this->activityLogger->log(
                'document.upload_failed',
                $user,
                $document->project,
                $document,
                [
                    'document_id' => $document->getKey(),
                    'reason' => class_basename($exception),
                ],
                $request,
            );

            throw $exception;
        }
    }
}
