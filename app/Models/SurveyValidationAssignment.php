<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SurveyValidationAssignment extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_LINK_GENERATED = 'link_generated';

    public const STATUS_OPENED = 'opened';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_LINK_GENERATED,
        self::STATUS_OPENED,
        self::STATUS_SUBMITTED,
        self::STATUS_REVIEWED,
        self::STATUS_EXPIRED,
        self::STATUS_REVOKED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_LINK_GENERATED => 'Link Generated',
        self::STATUS_OPENED => 'Opened',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_REVIEWED => 'Reviewed',
        self::STATUS_EXPIRED => 'Expired',
        self::STATUS_REVOKED => 'Revoked',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_validation_round_id',
        'expert_validator_id',
        'role',
        'status',
        'token_hash',
        'token_created_at',
        'opened_at',
        'submitted_at',
        'revoked_at',
        'expires_at',
        'created_by',
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

    public function round(): BelongsTo
    {
        return $this->belongsTo(SurveyValidationRound::class, 'survey_validation_round_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(ExpertValidator::class, 'expert_validator_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SurveyValidationScore::class);
    }

    public function recommendation(): HasOne
    {
        return $this->hasOne(SurveyValidationRecommendation::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SurveyValidationRevision::class, 'source_assignment_id');
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
        return $this->submitted_at !== null || in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_REVIEWED], true);
    }

    public function isAccessible(): bool
    {
        return filled($this->token_hash)
            && ! $this->isExpired()
            && ! $this->isRevoked()
            && ! $this->isSubmitted()
            && $this->round?->isAvailableForPublicValidation();
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
    }

    public function markSubmitted(): void
    {
        $this->forceFill([
            'submitted_at' => now(),
            'status' => self::STATUS_SUBMITTED,
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
