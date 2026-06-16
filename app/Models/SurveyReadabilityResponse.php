<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyReadabilityResponse extends Model
{
    use HasFactory, HasUuids;

    public const DECISION_EASY = 'easy_to_understand';

    public const DECISION_MINOR_REVISION = 'understandable_with_minor_revision';

    public const DECISION_NEEDS_REVISION = 'needs_revision';

    public const DECISION_DIFFICULT = 'difficult_to_understand';

    public const DECISIONS = [
        self::DECISION_EASY,
        self::DECISION_MINOR_REVISION,
        self::DECISION_NEEDS_REVISION,
        self::DECISION_DIFFICULT,
    ];

    public const DECISION_LABELS = [
        self::DECISION_EASY => 'Easy to understand',
        self::DECISION_MINOR_REVISION => 'Understandable with minor revision',
        self::DECISION_NEEDS_REVISION => 'Needs revision',
        self::DECISION_DIFFICULT => 'Difficult to understand',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_readability_participant_id',
        'survey_readability_round_id',
        'survey_id',
        'overall_clarity_score',
        'overall_length_score',
        'terminology_clarity_score',
        'answer_option_clarity_score',
        'instruction_clarity_score',
        'estimated_completion_minutes',
        'has_confusing_items',
        'confusing_items',
        'general_comments',
        'revision_suggestions',
        'final_decision',
    ];

    protected function casts(): array
    {
        return [
            'overall_clarity_score' => 'integer',
            'overall_length_score' => 'integer',
            'terminology_clarity_score' => 'integer',
            'answer_option_clarity_score' => 'integer',
            'instruction_clarity_score' => 'integer',
            'estimated_completion_minutes' => 'integer',
            'has_confusing_items' => 'boolean',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(SurveyReadabilityParticipant::class, 'survey_readability_participant_id');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(SurveyReadabilityRound::class, 'survey_readability_round_id');
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function questionFeedback(): HasMany
    {
        return $this->hasMany(SurveyReadabilityQuestionFeedback::class);
    }
}
