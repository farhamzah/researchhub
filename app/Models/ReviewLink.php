<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewLink extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_DISABLED = 'disabled';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_EXPIRED,
        self::STATUS_REVOKED,
        self::STATUS_DISABLED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'project_id',
        'document_id',
        'document_version_id',
        'created_by',
        'token_hash',
        'password_hash',
        'label',
        'reviewer_name',
        'reviewer_email',
        'permissions',
        'status',
        'expires_at',
        'revoked_at',
        'max_access_count',
        'access_count',
        'last_accessed_at',
    ];

    protected $hidden = [
        'token_hash',
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'access_count' => 'integer',
            'max_access_count' => 'integer',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'project_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(ReviewLinkAccessLog::class);
    }

    public function allows(string $permission): bool
    {
        return (bool) ($this->permissions[$permission] ?? false);
    }

    public function hasPassword(): bool
    {
        return filled($this->password_hash);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null || $this->status === self::STATUS_REVOKED;
    }

    public function accessLimitReached(): bool
    {
        return $this->max_access_count !== null && $this->access_count >= $this->max_access_count;
    }

    public function isReviewable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ! $this->isExpired()
            && ! $this->isRevoked()
            && ! $this->accessLimitReached();
    }

    public function markAccessed(): void
    {
        $this->forceFill([
            'access_count' => $this->access_count + 1,
            'last_accessed_at' => now(),
        ])->save();
    }

    public function markRevoked(): void
    {
        $this->forceFill([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
        ])->save();
    }
}
