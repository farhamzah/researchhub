<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisionFeedback extends Model
{
    use HasFactory, HasUuids;

    public const DECISION_APPROVED = 'approved';

    public const DECISION_MINOR_REVISION = 'minor_revision';

    public const DECISION_MAJOR_REVISION = 'major_revision';

    public const DECISION_NEEDS_DISCUSSION = 'needs_discussion';

    public const DECISION_REJECTED = 'rejected';

    public const DECISIONS = [
        self::DECISION_APPROVED,
        self::DECISION_MINOR_REVISION,
        self::DECISION_MAJOR_REVISION,
        self::DECISION_NEEDS_DISCUSSION,
        self::DECISION_REJECTED,
    ];

    public const DECISION_LABELS = [
        self::DECISION_APPROVED => 'Approved',
        self::DECISION_MINOR_REVISION => 'Minor Revision',
        self::DECISION_MAJOR_REVISION => 'Major Revision',
        self::DECISION_NEEDS_DISCUSSION => 'Needs Discussion',
        self::DECISION_REJECTED => 'Rejected',
    ];

    protected $table = 'supervision_feedback';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'supervision_review_link_id',
        'supervision_session_id',
        'decision',
        'general_feedback',
        'revision_notes',
        'recommended_next_steps',
        'supervisor_note',
    ];

    public function reviewLink(): BelongsTo
    {
        return $this->belongsTo(SupervisionReviewLink::class, 'supervision_review_link_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SupervisionSession::class, 'supervision_session_id');
    }
}
