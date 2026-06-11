<?php

namespace App\Modules\ReviewLinks\Services;

use App\Models\ReviewLink;
use App\Models\ReviewLinkAccessLog;
use Illuminate\Http\Request;

class ReviewLinkAccessLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        ?ReviewLink $reviewLink,
        string $action,
        string $result,
        ?Request $request = null,
        array $metadata = [],
    ): ReviewLinkAccessLog {
        return ReviewLinkAccessLog::create([
            'review_link_id' => $reviewLink?->getKey(),
            'project_id' => $reviewLink?->project_id,
            'document_id' => $reviewLink?->document_id,
            'action' => $action,
            'result' => $result,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $this->sanitizeMetadata($metadata) ?: null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->reject(fn (mixed $value, string $key): bool => str_contains(strtolower($key), 'token')
                || str_contains(strtolower($key), 'password'))
            ->all();
    }
}
