<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpertValidatorProject extends Model
{
    use HasUuids;

    public const ROLE_CONTENT = 'content_expert';

    public const ROLE_METHODS = 'methods_expert';

    public const ROLE_LANGUAGE = 'language_expert';

    public const ROLE_INSTRUMENT = 'instrument_expert';

    public const ROLE_SUPERVISOR = 'supervisor';

    public const ROLES = [
        self::ROLE_CONTENT,
        self::ROLE_METHODS,
        self::ROLE_LANGUAGE,
        self::ROLE_INSTRUMENT,
        self::ROLE_SUPERVISOR,
    ];

    public const ROLE_LABELS = [
        self::ROLE_CONTENT => 'Content Expert',
        self::ROLE_METHODS => 'Methods Expert',
        self::ROLE_LANGUAGE => 'Language Expert',
        self::ROLE_INSTRUMENT => 'Instrument Expert',
        self::ROLE_SUPERVISOR => 'Supervisor',
    ];

    public const STATUS_INVITED = 'invited';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DECLINED = 'declined';

    public const STATUSES = [
        self::STATUS_INVITED,
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
        self::STATUS_DECLINED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_INVITED => 'Invited',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_DECLINED => 'Declined',
    ];

    protected $table = 'expert_validator_project';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'research_project_id',
        'expert_validator_id',
        'role',
        'expertise_scope',
        'status',
        'invited_at',
        'accepted_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'research_project_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(ExpertValidator::class, 'expert_validator_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
