<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    use HasFactory, HasUuids;

    public const ROLE_OWNER = 'owner';
    public const ROLE_CO_RESEARCHER = 'co_researcher';
    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_CO_SUPERVISOR = 'co_supervisor';
    public const ROLE_EXAMINER = 'examiner';
    public const ROLE_VALIDATOR = 'validator';
    public const ROLE_ENUMERATOR = 'enumerator';
    public const ROLE_VIEWER = 'viewer';

    public const ROLES = [
        self::ROLE_OWNER,
        self::ROLE_CO_RESEARCHER,
        self::ROLE_SUPERVISOR,
        self::ROLE_CO_SUPERVISOR,
        self::ROLE_EXAMINER,
        self::ROLE_VALIDATOR,
        self::ROLE_ENUMERATOR,
        self::ROLE_VIEWER,
    ];

    public const STATUS_INVITED = 'invited';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_REVOKED = 'revoked';

    public const STATUSES = [
        self::STATUS_INVITED,
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_REVOKED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'project_id',
        'user_id',
        'email',
        'name',
        'role',
        'status',
        'invited_at',
        'accepted_at',
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
        return $this->belongsTo(ResearchProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
