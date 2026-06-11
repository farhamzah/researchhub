<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyQuestionScoring extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_id',
        'survey_question_id',
        'survey_indicator_id',
        'is_scored',
        'score_min',
        'score_max',
        'weight',
        'is_reverse_scored',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_scored' => 'boolean',
            'score_min' => 'decimal:4',
            'score_max' => 'decimal:4',
            'weight' => 'decimal:4',
            'is_reverse_scored' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(SurveyIndicator::class, 'survey_indicator_id');
    }
}
