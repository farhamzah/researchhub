<?php

namespace Tests\Support;

use App\Models\AnalysisResult;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\User;

class ReportExportFixture
{
    /**
     * @param  array<int, string>  $safeStrings
     * @param  array<int, string>  $sensitiveStrings
     * @param  array<int, string>  $forbiddenTerms
     */
    public function __construct(
        public readonly User $owner,
        public readonly ResearchProject $project,
        public readonly Survey $survey,
        public readonly AnalysisResult $result,
        public readonly array $safeStrings,
        public readonly array $sensitiveStrings,
        public readonly array $forbiddenTerms,
        public readonly string $rawPayloadMarker,
    ) {}
}
