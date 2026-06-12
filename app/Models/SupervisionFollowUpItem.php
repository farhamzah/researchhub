<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupervisionFollowUpItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_WAITING_SUPERVISOR = 'waiting_supervisor';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_TODO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_WAITING_SUPERVISOR,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_TODO => 'To Do',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_WAITING_SUPERVISOR => 'Waiting Supervisor',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    public const PRIORITY_LABELS = [
        self::PRIORITY_LOW => 'Low',
        self::PRIORITY_NORMAL => 'Normal',
        self::PRIORITY_HIGH => 'High',
        self::PRIORITY_URGENT => 'Urgent',
    ];

    public const SOURCE_SUPERVISOR_FEEDBACK = 'supervisor_feedback';

    public const SOURCE_SELF_NOTE = 'self_note';

    public const SOURCE_DOCUMENT_REVISION = 'document_revision';

    public const SOURCE_TIMELINE = 'timeline';

    public const SOURCE_VALIDATION_RESULT = 'validation_result';

    public const SOURCE_OTHER = 'other';

    public const SOURCES = [
        self::SOURCE_SUPERVISOR_FEEDBACK,
        self::SOURCE_SELF_NOTE,
        self::SOURCE_DOCUMENT_REVISION,
        self::SOURCE_TIMELINE,
        self::SOURCE_VALIDATION_RESULT,
        self::SOURCE_OTHER,
    ];

    public const SOURCE_LABELS = [
        self::SOURCE_SUPERVISOR_FEEDBACK => 'Supervisor Feedback',
        self::SOURCE_SELF_NOTE => 'Self Note',
        self::SOURCE_DOCUMENT_REVISION => 'Document Revision',
        self::SOURCE_TIMELINE => 'Timeline',
        self::SOURCE_VALIDATION_RESULT => 'Validation Result',
        self::SOURCE_OTHER => 'Other',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'supervision_session_id',
        'created_by',
        'assigned_to',
        'title',
        'description',
        'source',
        'status',
        'priority',
        'due_date',
        'completed_at',
        'completion_note',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SupervisionFollowUpItem $item): void {
            if ($item->status === self::STATUS_COMPLETED && $item->completed_at === null) {
                $item->completed_at = now();
            }

            if ($item->status !== self::STATUS_COMPLETED) {
                $item->completed_at = null;
            }
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SupervisionSession::class, 'supervision_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
