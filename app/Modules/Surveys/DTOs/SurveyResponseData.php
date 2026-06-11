<?php

namespace App\Modules\Surveys\DTOs;

class SurveyResponseData
{
    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $identity
     */
    public function __construct(
        public readonly array $answers,
        public readonly array $identity = [],
    ) {}
}
