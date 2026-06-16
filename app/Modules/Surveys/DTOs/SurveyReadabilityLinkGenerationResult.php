<?php

namespace App\Modules\Surveys\DTOs;

use App\Models\SurveyReadabilityParticipant;

class SurveyReadabilityLinkGenerationResult
{
    public function __construct(
        public readonly SurveyReadabilityParticipant $participant,
        public readonly string $rawToken,
        public readonly string $url,
    ) {}
}
