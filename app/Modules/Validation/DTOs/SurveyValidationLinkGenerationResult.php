<?php

namespace App\Modules\Validation\DTOs;

use App\Models\SurveyValidationAssignment;

class SurveyValidationLinkGenerationResult
{
    public function __construct(
        public readonly SurveyValidationAssignment $assignment,
        public readonly string $rawToken,
        public readonly string $url,
    ) {}
}
