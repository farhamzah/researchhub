<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ResearchProject extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_PAUSED,
        self::STATUS_COMPLETED,
        self::STATUS_ARCHIVED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'owner_id',
        'title',
        'slug',
        'description',
        'research_type',
        'institution',
        'status',
        'started_at',
        'target_finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'target_finished_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ResearchProject $project): void {
            if (blank($project->slug)) {
                $project->slug = Str::slug($project->title);
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class, 'project_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', ProjectMember::STATUS_ACTIVE);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'project_id');
    }

    public function driveFolders(): HasMany
    {
        return $this->hasMany(DriveFolder::class, 'project_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'project_id');
    }

    public function reviewLinks(): HasMany
    {
        return $this->hasMany(ReviewLink::class, 'project_id');
    }

    public function researchLinks(): HasMany
    {
        return $this->hasMany(ResearchLink::class, 'research_project_id');
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class, 'project_id');
    }

    public function analysisJobs(): HasMany
    {
        return $this->hasMany(AnalysisJob::class, 'project_id');
    }

    public function analysisResults(): HasMany
    {
        return $this->hasMany(AnalysisResult::class, 'project_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class, 'research_project_id')->orderBy('sort_order');
    }

    public function timelineTasks(): HasMany
    {
        return $this->hasMany(ProjectTimelineTask::class, 'research_project_id')->orderBy('sort_order');
    }

    public function respondents(): HasMany
    {
        return $this->hasMany(Respondent::class, 'project_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        return $query
            ->where('owner_id', $user->getKey())
            ->orWhereHas('activeMembers', fn ($memberQuery) => $memberQuery->where('user_id', $user->getKey()));
    }

    public function hasActiveMember(User $user): bool
    {
        return $this->activeMembers()
            ->where('user_id', $user->getKey())
            ->exists();
    }

    public function hasActiveMemberWithRole(User $user, array $roles): bool
    {
        return $this->activeMembers()
            ->where('user_id', $user->getKey())
            ->whereIn('role', $roles)
            ->exists();
    }
}
