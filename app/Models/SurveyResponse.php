<?php

namespace App\Models;

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

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }
}
