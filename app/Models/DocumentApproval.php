<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentApproval extends Model
{
    use HasFactory, HasUuids;

    public const DECISION_APPROVED = 'approved';

    public const DECISION_REVISION_REQUIRED = 'revision_required';

    public const DECISION_REJECTED = 'rejected';

    public const DECISIONS = [
        self::DECISION_APPROVED,
        self::DECISION_REVISION_REQUIRED,
        self::DECISION_REJECTED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'document_id',
        'document_version_id',
        'reviewer_id',
        'reviewer_name',
        'reviewer_email',
        'decision',
        'notes',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
