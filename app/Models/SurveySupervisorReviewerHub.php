<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveySupervisorReviewerHub extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'research_project_id',
        'created_by',
        'supervisor_name',
        'supervisor_email',
        'supervisor_code',
        'token_hash',
        'token_created_at',
        'opened_at',
        'revoked_at',
        'expires_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'token_created_at' => 'datetime',
            'opened_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'research_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewers(): HasMany
    {
        return $this->hasMany(SurveySupervisorReviewer::class, 'reviewer_hub_id');
    }

    public function isAccessible(): bool
    {
        return filled($this->token_hash)
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    public function markOpened(): void
    {
        if ($this->opened_at !== null || ! $this->isAccessible()) {
            return;
        }

        $this->forceFill(['opened_at' => now()])->save();
    }

    public function overallStatus(): string
    {
        $reviewers = $this->relationLoaded('reviewers') ? $this->reviewers : $this->reviewers()->get();

        if ($reviewers->isNotEmpty() && $reviewers->every->isSubmitted()) {
            return self::STATUS_COMPLETED;
        }

        if ($reviewers->contains(fn (SurveySupervisorReviewer $reviewer): bool => $reviewer->status !== SurveySupervisorReviewer::STATUS_NOT_OPENED)) {
            return self::STATUS_IN_PROGRESS;
        }

        return self::STATUS_NOT_STARTED;
    }
}
