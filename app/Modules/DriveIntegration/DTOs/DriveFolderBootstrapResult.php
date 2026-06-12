<?php

namespace App\Modules\DriveIntegration\DTOs;

use App\Models\DriveFolder;

final readonly class DriveFolderBootstrapResult
{
    /**
     * @param  array<int, DriveFolder>  $folders
     * @param  array<int, string>  $createdKeys
     * @param  array<int, string>  $reusedKeys
     */
    public function __construct(
        public array $folders,
        public array $createdKeys,
        public array $reusedKeys,
    ) {}

    public function createdCount(): int
    {
        return count($this->createdKeys);
    }

    public function reusedCount(): int
    {
        return count($this->reusedKeys);
    }
}
