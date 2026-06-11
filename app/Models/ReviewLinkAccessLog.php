<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewLinkAccessLog extends Model
{
    use HasFactory, HasUuids;

    public const ACTION_ACCESS_ATTEMPT = 'access_attempt';

    public const ACTION_ACCESS_GRANTED = 'access_granted';

    public const ACTION_ACCESS_DENIED = 'access_denied';

    public const ACTION_PASSWORD_REQUIRED = 'password_required';

    public const ACTION_PASSWORD_FAILED = 'password_failed';

    public const ACTION_PASSWORD_PASSED = 'password_passed';

    public const ACTION_COMMENT_CREATED = 'comment_created';

    public const ACTION_APPROVAL_CREATED = 'approval_created';

    public const ACTION_DOWNLOAD_REQUESTED = 'download_requested';

    public const ACTION_EXPIRED = 'expired';

    public const ACTION_REVOKED = 'revoked';

    public const ACTION_INVALID_TOKEN = 'invalid_token';

    public const RESULT_ALLOWED = 'allowed';

    public const RESULT_BLOCKED = 'blocked';

    public const RESULT_FAILED = 'failed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'review_link_id',
        'project_id',
        'document_id',
        'action',
        'result',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function reviewLink(): BelongsTo
    {
        return $this->belongsTo(ReviewLink::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'project_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
