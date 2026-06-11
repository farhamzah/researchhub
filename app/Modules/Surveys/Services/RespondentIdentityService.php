<?php

namespace App\Modules\Surveys\Services;

use App\Models\Respondent;
use App\Models\Survey;

class RespondentIdentityService
{
    /**
     * @param  array<string, mixed>  $identity
     */
    public function createForSurvey(Survey $survey, array $identity): ?Respondent
    {
        if ($survey->identity_mode === Survey::IDENTITY_ANONYMOUS) {
            return null;
        }

        if ($survey->identity_mode === Survey::IDENTITY_PSEUDONYM) {
            return Respondent::create([
                'project_id' => $survey->project_id,
                'survey_id' => $survey->getKey(),
                'pseudonym_code' => $this->nextPseudonymCode($survey),
                'institution' => $this->cleanNullable($identity['institution'] ?? null),
                'metadata' => $this->metadata($survey),
            ]);
        }

        $hasIdentity = collect(['name', 'email', 'identifier', 'institution'])
            ->contains(fn (string $key): bool => filled($identity[$key] ?? null));

        if (! $hasIdentity) {
            return null;
        }

        return Respondent::create([
            'project_id' => $survey->project_id,
            'survey_id' => $survey->getKey(),
            'name' => $this->cleanNullable($identity['name'] ?? null),
            'email' => $this->cleanNullable($identity['email'] ?? null),
            'identifier' => $this->cleanNullable($identity['identifier'] ?? null),
            'institution' => $this->cleanNullable($identity['institution'] ?? null),
            'metadata' => $this->metadata($survey),
        ]);
    }

    private function nextPseudonymCode(Survey $survey): string
    {
        $next = $survey->respondents()->whereNotNull('pseudonym_code')->count() + 1;

        return 'R'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function cleanNullable(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, string>
     */
    private function metadata(Survey $survey): array
    {
        return [
            'identity_mode' => $survey->identity_mode,
        ];
    }
}
