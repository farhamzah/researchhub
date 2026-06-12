<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyValidationRound extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const METHOD_EXPERT_JUDGMENT = 'expert_judgment';

    public const METHOD_AIKEN_V_READY = 'aiken_v_ready';

    public const METHOD_CVI_READY = 'cvi_ready';

    public const METHOD_CVR_READY = 'cvr_ready';

    public const METHOD_QUALITATIVE = 'qualitative';

    public const METHODS = [
        self::METHOD_EXPERT_JUDGMENT,
        self::METHOD_AIKEN_V_READY,
        self::METHOD_CVI_READY,
        self::METHOD_CVR_READY,
        self::METHOD_QUALITATIVE,
    ];

    public const METHOD_LABELS = [
        self::METHOD_EXPERT_JUDGMENT => 'Expert Judgment',
        self::METHOD_AIKEN_V_READY => "Aiken's V Ready",
        self::METHOD_CVI_READY => 'CVI Ready',
        self::METHOD_CVR_READY => 'CVR Ready',
        self::METHOD_QUALITATIVE => 'Qualitative',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_ARCHIVED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_OPEN => 'Open',
        self::STATUS_CLOSED => 'Closed',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_id',
        'research_project_id',
        'created_by',
        'title',
        'description',
        'method',
        'rating_scale_min',
        'rating_scale_max',
        'status',
        'instructions',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'rating_scale_min' => 'integer',
            'rating_scale_max' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'research_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SurveyValidationAssignment::class);
    }

    public function isAvailableForPublicValidation(): bool
    {
        if ($this->status !== self::STATUS_OPEN) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        return ! ($this->ends_at !== null && $this->ends_at->isPast());
    }
}
