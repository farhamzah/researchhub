<?php

namespace App\Modules\Documents\DTOs;

use App\Models\DocumentVersion;

final readonly class DocumentUploadData
{
    public function __construct(
        public string $fileName,
        public string $mimeType,
        public ?string $originalFileName = null,
        public ?string $fileExtension = null,
        public ?int $fileSize = null,
        public ?string $checksum = null,
        public ?string $driveFileId = null,
        public ?string $driveFolderId = null,
        public ?string $webViewLink = null,
        public ?string $webDownloadLink = null,
        public string $storageStatus = DocumentVersion::STORAGE_STATUS_PENDING,
        public ?string $notes = null,
    ) {}

    public function withSanitizedNames(): self
    {
        return new self(
            fileName: $this->sanitizeFileName($this->fileName),
            mimeType: $this->mimeType,
            originalFileName: $this->originalFileName === null ? null : $this->sanitizeFileName($this->originalFileName),
            fileExtension: $this->fileExtension,
            fileSize: $this->fileSize,
            checksum: $this->checksum,
            driveFileId: $this->driveFileId,
            driveFolderId: $this->driveFolderId,
            webViewLink: $this->webViewLink,
            webDownloadLink: $this->webDownloadLink,
            storageStatus: $this->storageStatus,
            notes: $this->notes,
        );
    }

    private function sanitizeFileName(string $fileName): string
    {
        return trim(basename(str_replace('\\', '/', $fileName))) ?: 'document-file';
    }
}
