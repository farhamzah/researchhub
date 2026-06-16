<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyDistributionRecipient extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_LINK_READY = 'link_ready';

    public const STATUS_SENT_MANUALLY = 'sent_manually';

    public const STATUS_OPENED = 'opened';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_LINK_READY,
        self::STATUS_SENT_MANUALLY,
        self::STATUS_OPENED,
        self::STATUS_SUBMITTED,
        self::STATUS_REVOKED,
        self::STATUS_CLOSED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_LINK_READY => 'Link ready',
        self::STATUS_SENT_MANUALLY => 'Sent manually',
        self::STATUS_OPENED => 'Opened',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_REVOKED => 'Revoked',
        self::STATUS_CLOSED => 'Closed',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'batch_id',
        'target_survey_id',
        'validation_assignment_id',
        'readability_participant_id',
        'name',
        'email',
        'phone',
        'institution',
        'role_label',
        'link_url',
        'status',
        'sent_at',
        'opened_at',
        'submitted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SurveyDistributionBatch::class, 'batch_id');
    }

    public function targetSurvey(): BelongsTo
    {
        return $this->belongsTo(Survey::class, 'target_survey_id');
    }

    public function validationAssignment(): BelongsTo
    {
        return $this->belongsTo(SurveyValidationAssignment::class, 'validation_assignment_id');
    }

    public function readabilityParticipant(): BelongsTo
    {
        return $this->belongsTo(SurveyReadabilityParticipant::class, 'readability_participant_id');
    }
}
