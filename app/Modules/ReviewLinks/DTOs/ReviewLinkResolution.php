<?php

namespace App\Modules\ReviewLinks\DTOs;

use App\Models\ReviewLink;

final readonly class ReviewLinkResolution
{
    public function __construct(
        public ?ReviewLink $reviewLink,
        public string $status,
        public string $message,
        public bool $allowed = false,
        public bool $requiresPassword = false,
    ) {}
}
