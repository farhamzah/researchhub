<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyResponse extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_STARTED = 'started';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_VOID = 'void';

    public const STATUSES = [
        self::STATUS_STARTED,
        self::STATUS_SUBMITTED,
        self::STATUS_VOID,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_id',
        'respondent_id',
        'response_token_hash',
        'status',
        'submitted_at',
        'ip_address',
        'user_agent',
        'score_total',
        'is_test_response',
        'test_label',
        'pilot_run_id',
        'excluded_from_analysis',
        'metadata',
    ];

    protected $hidden = [
        'response_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'score_total' => 'decimal:2',
            'is_test_response' => 'boolean',
            'excluded_from_analysis' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(Respondent::class);
    }

    public function pilotRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisPilotRun::class, 'pilot_run_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeOfficial(Builder $query): Builder
    {
        return $query
            ->where('is_test_response', false)
            ->where('excluded_from_analysis', false);
    }

    public function scopeTestData(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $testQuery): void {
                $testQuery
                    ->where('is_test_response', true)
                    ->orWhere('excluded_from_analysis', true);
            });
    }
}
