<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyValidationScore extends Model
{
    use HasFactory, HasUuids;

    public const RECOMMENDATION_ACCEPTED = 'accepted';

    public const RECOMMENDATION_MINOR_REVISION = 'minor_revision';

    public const RECOMMENDATION_MAJOR_REVISION = 'major_revision';

    public const RECOMMENDATION_REJECTED = 'rejected';

    public const RECOMMENDATIONS = [
        self::RECOMMENDATION_ACCEPTED,
        self::RECOMMENDATION_MINOR_REVISION,
        self::RECOMMENDATION_MAJOR_REVISION,
        self::RECOMMENDATION_REJECTED,
    ];

    public const RECOMMENDATION_LABELS = [
        self::RECOMMENDATION_ACCEPTED => 'Diterima',
        self::RECOMMENDATION_MINOR_REVISION => 'Revisi kecil',
        self::RECOMMENDATION_MAJOR_REVISION => 'Revisi besar',
        self::RECOMMENDATION_REJECTED => 'Ditolak',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_validation_assignment_id',
        'survey_question_id',
        'content_relevance_score',
        'language_clarity_score',
        'construct_alignment_score',
        'measurability_score',
        'feasibility_score',
        'ethical_suitability_score',
        'relevance_score',
        'clarity_score',
        'language_score',
        'appropriateness_score',
        'comment',
        'recommendation',
    ];

    protected function casts(): array
    {
        return [
            'content_relevance_score' => 'integer',
            'language_clarity_score' => 'integer',
            'construct_alignment_score' => 'integer',
            'measurability_score' => 'integer',
            'feasibility_score' => 'integer',
            'ethical_suitability_score' => 'integer',
            'relevance_score' => 'integer',
            'clarity_score' => 'integer',
            'language_score' => 'integer',
            'appropriateness_score' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SurveyValidationAssignment::class, 'survey_validation_assignment_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }
}
