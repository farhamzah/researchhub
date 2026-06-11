<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyIndicator extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_id',
        'survey_scale_id',
        'name',
        'slug',
        'description',
        'interpretation_rules',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'interpretation_rules' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function scale(): BelongsTo
    {
        return $this->belongsTo(SurveyScale::class, 'survey_scale_id');
    }

    public function questionScorings(): HasMany
    {
        return $this->hasMany(SurveyQuestionScoring::class)->with('question');
    }
}
