<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveySupervisorReviewRound extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_OPEN,
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_OPEN => 'Open',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CLOSED => 'Closed',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_id',
        'research_project_id',
        'created_by',
        'title',
        'purpose',
        'status',
        'due_date',
        'opened_at',
        'closed_at',
        'snapshot_taken_at',
        'snapshot_hash',
        'snapshot_json',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'snapshot_taken_at' => 'datetime',
            'snapshot_json' => 'array',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
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
        return $this->hasMany(SurveySupervisorReviewer::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SurveySupervisorReviewComment::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SurveySupervisorReviewRevision::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
