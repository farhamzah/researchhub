<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SurveyQuestion extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_SHORT_TEXT = 'short_text';

    public const TYPE_LONG_TEXT = 'long_text';

    public const TYPE_SINGLE_CHOICE = 'single_choice';

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public const TYPE_LIKERT = 'likert';

    public const TYPE_LIKERT_MATRIX = 'likert_matrix';

    public const TYPE_NUMBER = 'number';

    public const TYPE_DATE = 'date';

    public const TYPE_CONSENT = 'consent';

    public const TYPE_HIDDEN = 'hidden';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_id',
        'page_id',
        'question_key',
        'type',
        'label',
        'help_text',
        'options',
        'settings',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'settings' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SurveyPage::class, 'page_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }

    public function scoring(): HasOne
    {
        return $this->hasOne(SurveyQuestionScoring::class);
    }

    public function validationScores(): HasMany
    {
        return $this->hasMany(SurveyValidationScore::class);
    }

    public function readabilityFeedback(): HasMany
    {
        return $this->hasMany(SurveyReadabilityQuestionFeedback::class);
    }
}
