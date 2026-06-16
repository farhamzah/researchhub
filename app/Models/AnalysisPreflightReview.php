<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisPreflightReview extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FAILED = 'failed';

    public const STATUS_WARNING = 'warning';

    public const STATUS_READY = 'ready';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_FAILED,
        self::STATUS_WARNING,
        self::STATUS_READY,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_id',
        'project_id',
        'status',
        'total_checks',
        'passed_checks',
        'warning_checks',
        'failed_checks',
        'reviewed_by',
        'reviewed_at',
        'ready_marked_at',
        'notes',
        'snapshot_json',
    ];

    protected function casts(): array
    {
        return [
            'total_checks' => 'integer',
            'passed_checks' => 'integer',
            'warning_checks' => 'integer',
            'failed_checks' => 'integer',
            'reviewed_at' => 'datetime',
            'ready_marked_at' => 'datetime',
            'snapshot_json' => 'array',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'project_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
