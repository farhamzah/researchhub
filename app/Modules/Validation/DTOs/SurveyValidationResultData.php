<?php

namespace App\Modules\Validation\DTOs;

use App\Models\SurveyValidationRound;

class SurveyValidationResultData
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<int, array<string, mixed>>  $validators
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $comments
     */
    public function __construct(
        public readonly SurveyValidationRound $round,
        public readonly array $summary,
        public readonly array $validators,
        public readonly array $items,
        public readonly array $comments,
        public readonly string $narrative,
        public readonly string $cvrNote,
    ) {}
}
