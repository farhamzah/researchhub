<?php

namespace App\Modules\DriveIntegration\DTOs;

final readonly class DriveFolderData
{
    public function __construct(
        public string $driveFolderId,
        public string $name,
        public ?string $webViewLink = null,
    ) {}
}
