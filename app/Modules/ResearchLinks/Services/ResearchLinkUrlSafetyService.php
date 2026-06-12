<?php

namespace App\Modules\ResearchLinks\Services;

use Illuminate\Validation\ValidationException;

class ResearchLinkUrlSafetyService
{
    private const ALLOWED_SCHEMES = [
        'http',
        'https',
    ];

    public function assertSafe(?string $url, string $field = 'url'): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true) || blank($host) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages([
                $field => 'Only valid http and https URLs are allowed.',
            ]);
        }

        return $url;
    }

    public function host(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        return parse_url(trim($url), PHP_URL_HOST) ?: null;
    }
}
