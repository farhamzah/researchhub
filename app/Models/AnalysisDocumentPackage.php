<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisDocumentPackage extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_FINAL = 'final';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_REVIEWED,
        self::STATUS_FINAL,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_REVIEWED => 'Reviewed',
        self::STATUS_FINAL => 'Final',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_id',
        'project_id',
        'title',
        'document_code',
        'version',
        'document_date',
        'researcher_name',
        'researcher_identifier',
        'institution',
        'study_program',
        'promoter_name',
        'co_promoter_names',
        'stage',
        'status',
        'purpose_text',
        'notes',
        'snapshot_json',
        'finalized_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'snapshot_json' => 'array',
            'finalized_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
