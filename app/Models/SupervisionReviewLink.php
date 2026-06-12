<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupervisionReviewLink extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_LINK_GENERATED = 'link_generated';

    public const STATUS_OPENED = 'opened';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_LINK_GENERATED,
        self::STATUS_OPENED,
        self::STATUS_SUBMITTED,
        self::STATUS_EXPIRED,
        self::STATUS_REVOKED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_LINK_GENERATED => 'Link Generated',
        self::STATUS_OPENED => 'Opened',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_EXPIRED => 'Expired',
        self::STATUS_REVOKED => 'Revoked',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'supervision_session_id',
        'expert_validator_id',
        'created_by',
        'recipient_name',
        'recipient_role',
        'status',
        'token_hash',
        'token_created_at',
        'opened_at',
        'submitted_at',
        'revoked_at',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'token_created_at' => 'datetime',
            'opened_at' => 'datetime',
            'submitted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SupervisionSession::class, 'supervision_session_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(ExpertValidator::class, 'expert_validator_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(SupervisionFeedback::class);
    }

    public function recipientDisplayName(): string
    {
        return $this->validator?->name ?: ($this->recipient_name ?: 'Supervisor');
    }

    public function recipientDisplayRole(): string
    {
        return $this->recipient_role ?: 'Supervisor';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null || $this->status === self::STATUS_REVOKED;
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null || $this->status === self::STATUS_SUBMITTED;
    }

    public function isAccessible(): bool
    {
        return filled($this->token_hash)
            && ! $this->isExpired()
            && ! $this->isRevoked()
            && ! $this->isSubmitted();
    }

    public function markOpened(): void
    {
        if ($this->opened_at !== null || $this->isSubmitted() || $this->isRevoked() || $this->isExpired()) {
            return;
        }

        $this->forceFill([
            'opened_at' => now(),
            'status' => self::STATUS_OPENED,
        ])->save();

        if ($this->session && $this->session->status === SupervisionSession::STATUS_SHARED) {
            $this->session->forceFill(['status' => SupervisionSession::STATUS_OPENED])->save();
        }
    }

    public function markSubmitted(string $decision): void
    {
        $this->forceFill([
            'submitted_at' => now(),
            'status' => self::STATUS_SUBMITTED,
        ])->save();

        $sessionStatus = $decision === SupervisionFeedback::DECISION_APPROVED
            ? SupervisionSession::STATUS_APPROVED
            : SupervisionSession::STATUS_REVISION_NEEDED;

        $this->session?->forceFill([
            'status' => $sessionStatus,
            'submitted_at' => now(),
        ])->save();
    }

    public function markExpired(): void
    {
        if ($this->status !== self::STATUS_SUBMITTED) {
            $this->forceFill(['status' => self::STATUS_EXPIRED])->save();
        }
    }

    public function markRevoked(): void
    {
        $this->forceFill([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
        ])->save();
    }
}
