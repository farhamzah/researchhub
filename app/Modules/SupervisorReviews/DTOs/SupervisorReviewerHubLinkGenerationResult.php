<?php

namespace App\Modules\SupervisorReviews\DTOs;

use App\Models\SurveySupervisorReviewerHub;

readonly class SupervisorReviewerHubLinkGenerationResult
{
    public function __construct(
        public SurveySupervisorReviewerHub $hub,
        public string $rawToken,
        public string $url,
    ) {}
}
