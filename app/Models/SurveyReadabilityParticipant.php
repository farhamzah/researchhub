<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SurveyReadabilityParticipant extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_STUDENT = 'student';

    public const TYPE_LECTURER = 'lecturer';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_STUDENT,
        self::TYPE_LECTURER,
        self::TYPE_OTHER,
    ];

    public const TYPE_LABELS = [
        self::TYPE_STUDENT => 'Student',
        self::TYPE_LECTURER => 'Lecturer',
        self::TYPE_OTHER => 'Other',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_OPENED = 'opened';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_REVOKED = 'revoked';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_OPENED,
        self::STATUS_SUBMITTED,
        self::STATUS_REVOKED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_OPENED => 'Opened',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_REVOKED => 'Revoked',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_readability_round_id',
        'survey_id',
        'participant_name',
        'participant_email',
        'participant_type',
        'institution',
        'token_hash',
        'token_created_at',
        'status',
        'opened_at',
        'submitted_at',
        'revoked_at',
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
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(SurveyReadabilityRound::class, 'survey_readability_round_id');
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function response(): HasOne
    {
        return $this->hasOne(SurveyReadabilityResponse::class);
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
            && ! $this->isRevoked()
            && ! $this->isSubmitted()
            && $this->round?->isAvailableForPublicReview();
    }

    public function markOpened(): void
    {
        if ($this->opened_at !== null || $this->isSubmitted() || $this->isRevoked()) {
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

    public function markRevoked(): void
    {
        $this->forceFill([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
        ])->save();
    }
}
