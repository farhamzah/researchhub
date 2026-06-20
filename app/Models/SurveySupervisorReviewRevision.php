<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveySupervisorReviewRevision extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REVISED = 'revised';

    public const STATUS_REJECTED = 'rejected_with_reason';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_REVISED,
        self::STATUS_REJECTED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_ACCEPTED => 'Accepted',
        self::STATUS_REVISED => 'Revised',
        self::STATUS_REJECTED => 'Rejected with reason',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_id',
        'survey_supervisor_review_round_id',
        'survey_supervisor_reviewer_id',
        'survey_supervisor_review_comment_id',
        'item_label',
        'supervisor_code',
        'comment',
        'suggested_revision',
        'severity',
        'researcher_response',
        'action_taken',
        'status',
        'revised_version',
        'revised_at',
    ];

    protected function casts(): array
    {
        return [
            'revised_at' => 'datetime',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(SurveySupervisorReviewRound::class, 'survey_supervisor_review_round_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(SurveySupervisorReviewer::class, 'survey_supervisor_reviewer_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(SurveySupervisorReviewComment::class, 'survey_supervisor_review_comment_id');
    }
}
