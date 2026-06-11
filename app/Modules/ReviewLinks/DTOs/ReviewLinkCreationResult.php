<?php

namespace App\Modules\ReviewLinks\DTOs;

use App\Models\ReviewLink;

final readonly class ReviewLinkCreationResult
{
    public function __construct(
        public ReviewLink $reviewLink,
        public string $rawToken,
        public string $url,
    ) {}
}
