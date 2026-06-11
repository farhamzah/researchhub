<?php

namespace App\Modules\Surveys\Services;

use App\Models\Respondent;
use App\Models\Survey;
use App\Models\User;

class RespondentPrivacyService
{
    public const MODE_FULL = 'full';

    public const MODE_MASKED = 'masked';

    public const MODE_HIDDEN = 'hidden';

    public const MODE_ANONYMOUS = 'anonymous';

    public const MODE_PSEUDONYM = 'pseudonym';

    public const MODE_AUTO = 'auto';

    public function display(?Respondent $respondent, Survey $survey, ?User $viewer = null, string $mode = self::MODE_AUTO): string
    {
        $mode = $mode === self::MODE_AUTO ? $this->modeForSurvey($survey, $viewer) : $mode;

        return match ($mode) {
            self::MODE_FULL => $this->canViewFullIdentity($viewer, $survey)
                ? $this->fullDisplay($respondent)
                : $this->display($respondent, $survey, $viewer, self::MODE_HIDDEN),
            self::MODE_MASKED => $this->maskedDisplay($respondent),
            self::MODE_ANONYMOUS => 'Anonymous respondent',
            self::MODE_PSEUDONYM => $respondent?->pseudonym_code ?: 'Pseudonym respondent',
            default => 'Identity hidden',
        };
    }

    public function canViewFullIdentity(?User $viewer, Survey $survey): bool
    {
        if (! $viewer) {
            return false;
        }

        if ($viewer->hasRole('super_admin')) {
            return true;
        }

        return $survey->project->owner_id === $viewer->getKey()
            && $viewer->can('surveys.view_respondent_identity');
    }

    /**
     * @return array<string, string|null>
     */
    public function identityFields(?Respondent $respondent, Survey $survey, ?User $viewer): array
    {
        if (! $respondent || ! $this->canViewFullIdentity($viewer, $survey)) {
            return [];
        }

        return [
            'name' => $respondent->name,
            'email' => $respondent->email,
            'identifier' => $respondent->identifier,
            'institution' => $respondent->institution,
        ];
    }

    private function modeForSurvey(Survey $survey, ?User $viewer): string
    {
        return match ($survey->identity_mode) {
            Survey::IDENTITY_ANONYMOUS => self::MODE_ANONYMOUS,
            Survey::IDENTITY_PSEUDONYM => self::MODE_PSEUDONYM,
            Survey::IDENTITY_FULL => $this->canViewFullIdentity($viewer, $survey) ? self::MODE_FULL : self::MODE_MASKED,
            default => self::MODE_HIDDEN,
        };
    }

    private function fullDisplay(?Respondent $respondent): string
    {
        if (! $respondent) {
            return 'Identity not provided';
        }

        return collect([
            $respondent->name,
            $respondent->email,
            $respondent->identifier,
            $respondent->institution,
        ])
            ->filter()
            ->implode(' | ') ?: 'Identity not provided';
    }

    private function maskedDisplay(?Respondent $respondent): string
    {
        if (! $respondent) {
            return 'Identity hidden';
        }

        if (filled($respondent->email)) {
            return $this->maskEmail((string) $respondent->email);
        }

        if (filled($respondent->name)) {
            return $this->maskText((string) $respondent->name);
        }

        if (filled($respondent->identifier)) {
            return $this->maskText((string) $respondent->identifier);
        }

        return 'Identity hidden';
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = str_contains($email, '@') ? explode('@', $email, 2) : [$email, null];

        $maskedLocal = mb_substr($local, 0, 2).str_repeat('*', max(3, mb_strlen($local) - 2));

        return $domain ? "{$maskedLocal}@{$domain}" : $maskedLocal;
    }

    private function maskText(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return 'Identity hidden';
        }

        return mb_substr($value, 0, 2).str_repeat('*', max(3, mb_strlen($value) - 2));
    }
}
