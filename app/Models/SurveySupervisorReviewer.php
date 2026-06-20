<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveySupervisorReviewer extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_NOT_OPENED = 'not_opened';

    public const STATUS_OPENED = 'opened';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_NEEDS_FOLLOW_UP = 'needs_follow_up';

    public const STATUS_REVOKED = 'revoked';

    public const STATUSES = [
        self::STATUS_NOT_OPENED,
        self::STATUS_OPENED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_SUBMITTED,
        self::STATUS_NEEDS_FOLLOW_UP,
        self::STATUS_REVOKED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_NOT_OPENED => 'Not Opened',
        self::STATUS_OPENED => 'Opened',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_NEEDS_FOLLOW_UP => 'Needs Follow-up',
        self::STATUS_REVOKED => 'Revoked',
    ];

    public const DECISION_APPROVED = 'approved_for_expert_validation';

    public const DECISION_MINOR_REVISIONS = 'approved_with_minor_revisions';

    public const DECISION_REVISE_RESUBMIT = 'revise_and_resubmit';

    public const DECISION_NOT_APPROVED = 'not_approved_yet';

    public const DECISIONS = [
        self::DECISION_APPROVED,
        self::DECISION_MINOR_REVISIONS,
        self::DECISION_REVISE_RESUBMIT,
        self::DECISION_NOT_APPROVED,
    ];

    public const DECISION_LABELS = [
        self::DECISION_APPROVED => 'Approved for expert validation',
        self::DECISION_MINOR_REVISIONS => 'Approved with minor revisions',
        self::DECISION_REVISE_RESUBMIT => 'Revise and resubmit',
        self::DECISION_NOT_APPROVED => 'Not approved yet',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_supervisor_review_round_id',
        'supervisor_name',
        'supervisor_email',
        'supervisor_code',
        'role',
        'status',
        'token_hash',
        'token_created_at',
        'opened_at',
        'submitted_at',
        'revoked_at',
        'expires_at',
        'final_decision',
        'final_notes',
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
        return $this->belongsTo(SurveySupervisorReviewRound::class, 'survey_supervisor_review_round_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SurveySupervisorReviewComment::class, 'survey_supervisor_reviewer_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SurveySupervisorReviewRevision::class, 'survey_supervisor_reviewer_id');
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
            && $this->round?->isOpen();
    }

    public function markOpened(): void
    {
        if ($this->opened_at !== null || $this->isRevoked() || $this->isExpired()) {
            return;
        }

        $this->forceFill([
            'opened_at' => now(),
            'status' => self::STATUS_OPENED,
        ])->save();
    }

    public function markSubmitted(string $decision, ?string $notes): void
    {
        $this->forceFill([
            'submitted_at' => now(),
            'status' => self::STATUS_SUBMITTED,
            'final_decision' => $decision,
            'final_notes' => $notes,
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
