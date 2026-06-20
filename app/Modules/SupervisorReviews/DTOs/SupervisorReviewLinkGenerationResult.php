<?php

namespace App\Modules\SupervisorReviews\DTOs;

use App\Models\SurveySupervisorReviewer;

readonly class SupervisorReviewLinkGenerationResult
{
    public function __construct(
        public SurveySupervisorReviewer $reviewer,
        public string $rawToken,
        public string $url,
    ) {}
}
