<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyValidationRecommendation extends Model
{
    use HasFactory, HasUuids;

    public const DECISION_VALID_WITHOUT_REVISION = 'valid_without_revision';

    public const DECISION_VALID_WITH_MINOR_REVISION = 'valid_with_minor_revision';

    public const DECISION_VALID_WITH_MODERATE_REVISION = 'valid_with_moderate_revision';

    public const DECISION_MAJOR_REVISION_REQUIRED = 'major_revision_required';

    public const DECISION_NOT_VALID = 'not_valid';

    public const DECISIONS = [
        self::DECISION_VALID_WITHOUT_REVISION,
        self::DECISION_VALID_WITH_MINOR_REVISION,
        self::DECISION_VALID_WITH_MODERATE_REVISION,
        self::DECISION_MAJOR_REVISION_REQUIRED,
        self::DECISION_NOT_VALID,
    ];

    public const DECISION_LABELS = [
        self::DECISION_VALID_WITHOUT_REVISION => 'Valid without revision',
        self::DECISION_VALID_WITH_MINOR_REVISION => 'Valid with minor revision',
        self::DECISION_VALID_WITH_MODERATE_REVISION => 'Valid with moderate revision',
        self::DECISION_MAJOR_REVISION_REQUIRED => 'Major revision required',
        self::DECISION_NOT_VALID => 'Not valid',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_validation_assignment_id',
        'survey_id',
        'overall_score',
        'feasibility_decision',
        'general_comments',
        'revision_suggestions',
    ];

    protected function casts(): array
    {
        return [
            'overall_score' => 'decimal:2',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SurveyValidationAssignment::class, 'survey_validation_assignment_id');
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }
}
