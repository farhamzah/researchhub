<?php

namespace App\Modules\Documents\Services;

use App\Models\DocumentVersion;
use App\Modules\Documents\DTOs\DocumentUploadData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class DocumentFileValidationService
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.(int) config('researchhub_documents.max_upload_size_kb', 51200),
                'mimes:'.implode(',', config('researchhub_documents.allowed_extensions', [])),
                'mimetypes:'.implode(',', config('researchhub_documents.allowed_mime_types', [])),
            ],
        ];
    }

    public function validate(UploadedFile $file): void
    {
        Validator::make(['file' => $file], $this->rules())->validate();
    }

    public function metadataFromFile(UploadedFile $file): DocumentUploadData
    {
        $this->validate($file);

        return new DocumentUploadData(
            fileName: $file->hashName(),
            mimeType: $file->getMimeType() ?: 'application/octet-stream',
            originalFileName: $file->getClientOriginalName(),
            fileExtension: $file->getClientOriginalExtension(),
            fileSize: $file->getSize() ?: null,
            checksum: hash_file('sha256', $file->getRealPath()),
            storageStatus: DocumentVersion::STORAGE_STATUS_PENDING,
        );
    }
}
