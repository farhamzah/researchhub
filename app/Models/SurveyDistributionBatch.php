<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyDistributionBatch extends Model
{
    use HasFactory, HasUuids;

    public const AUDIENCE_STUDENT = 'student';

    public const AUDIENCE_LECTURER = 'lecturer';

    public const AUDIENCE_PRACTITIONER = 'practitioner';

    public const AUDIENCE_EXPERT_VALIDATOR = 'expert_validator';

    public const AUDIENCE_READABILITY_PARTICIPANT = 'readability_participant';

    public const AUDIENCE_SUPERVISOR = 'supervisor';

    public const AUDIENCES = [
        self::AUDIENCE_STUDENT,
        self::AUDIENCE_LECTURER,
        self::AUDIENCE_PRACTITIONER,
        self::AUDIENCE_EXPERT_VALIDATOR,
        self::AUDIENCE_READABILITY_PARTICIPANT,
        self::AUDIENCE_SUPERVISOR,
    ];

    public const AUDIENCE_LABELS = [
        self::AUDIENCE_STUDENT => 'Mahasiswa',
        self::AUDIENCE_LECTURER => 'Dosen',
        self::AUDIENCE_PRACTITIONER => 'Praktisi / Ahli CPOB',
        self::AUDIENCE_EXPERT_VALIDATOR => 'Validator Ahli',
        self::AUDIENCE_READABILITY_PARTICIPANT => 'Peserta Uji Keterbacaan',
        self::AUDIENCE_SUPERVISOR => 'Pembimbing / Promotor',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_SENT_MANUALLY = 'sent_manually';

    public const STATUS_FOLLOWED_UP = 'followed_up';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_READY,
        self::STATUS_SENT_MANUALLY,
        self::STATUS_FOLLOWED_UP,
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_READY => 'Ready',
        self::STATUS_SENT_MANUALLY => 'Sent manually',
        self::STATUS_FOLLOWED_UP => 'Followed up',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CLOSED => 'Closed',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_id',
        'project_id',
        'audience_type',
        'title',
        'message_subject',
        'message_body',
        'deadline',
        'status',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
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

    public function recipients(): HasMany
    {
        return $this->hasMany(SurveyDistributionRecipient::class, 'batch_id');
    }
}
