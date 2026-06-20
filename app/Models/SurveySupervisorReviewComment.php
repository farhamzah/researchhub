<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveySupervisorReviewComment extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_ITEM = 'item';

    public const TYPE_SECTION = 'section';

    public const TYPE_INTRO = 'intro';

    public const TYPE_OVERALL = 'overall';

    public const TYPES = [
        self::TYPE_ITEM,
        self::TYPE_SECTION,
        self::TYPE_INTRO,
        self::TYPE_OVERALL,
    ];

    public const SEVERITY_MINOR = 'minor';

    public const SEVERITY_MODERATE = 'moderate';

    public const SEVERITY_MAJOR = 'major';

    public const SEVERITIES = [
        self::SEVERITY_MINOR,
        self::SEVERITY_MODERATE,
        self::SEVERITY_MAJOR,
    ];

    public const DECISION_ACCEPT = 'accept';

    public const DECISION_REVISE = 'revise';

    public const DECISION_REMOVE = 'remove';

    public const DECISION_MERGE = 'merge';

    public const DECISION_CLARIFY = 'clarify';

    public const DECISIONS = [
        self::DECISION_ACCEPT,
        self::DECISION_REVISE,
        self::DECISION_REMOVE,
        self::DECISION_MERGE,
        self::DECISION_CLARIFY,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_supervisor_reviewer_id',
        'survey_supervisor_review_round_id',
        'survey_question_id',
        'comment_type',
        'target_key',
        'target_label',
        'comment',
        'suggested_revision',
        'severity',
        'decision',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(SurveySupervisorReviewer::class, 'survey_supervisor_reviewer_id');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(SurveySupervisorReviewRound::class, 'survey_supervisor_review_round_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }
}
