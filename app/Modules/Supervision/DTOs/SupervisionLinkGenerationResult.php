<?php

namespace App\Modules\Supervision\DTOs;

use App\Models\SupervisionReviewLink;

class SupervisionLinkGenerationResult
{
    public function __construct(
        public readonly SupervisionReviewLink $reviewLink,
        public readonly string $rawToken,
        public readonly string $url,
    ) {}
}
