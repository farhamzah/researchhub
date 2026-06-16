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

class Survey extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_CLOSED,
        self::STATUS_ARCHIVED,
    ];

    public const IDENTITY_FULL = 'full_identity';

    public const IDENTITY_HIDDEN = 'hidden_identity';

    public const IDENTITY_ANONYMOUS = 'anonymous';

    public const IDENTITY_PSEUDONYM = 'pseudonym';

    public const IDENTITY_MODES = [
        self::IDENTITY_FULL,
        self::IDENTITY_HIDDEN,
        self::IDENTITY_ANONYMOUS,
        self::IDENTITY_PSEUDONYM,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'project_id',
        'created_by',
        'title',
        'slug',
        'description',
        'schema',
        'status',
        'identity_mode',
        'is_public',
        'published_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'is_public' => 'boolean',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Survey $survey): void {
            if (blank($survey->slug)) {
                $survey->slug = Str::slug($survey->title);
            }

            if (blank($survey->status)) {
                $survey->status = self::STATUS_DRAFT;
            }

            if (blank($survey->identity_mode)) {
                $survey->identity_mode = self::IDENTITY_HIDDEN;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SurveyPage::class)->orderBy('sort_order');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('sort_order');
    }

    public function scales(): HasMany
    {
        return $this->hasMany(SurveyScale::class)->orderBy('sort_order');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(SurveyIndicator::class)->orderBy('sort_order');
    }

    public function questionScorings(): HasMany
    {
        return $this->hasMany(SurveyQuestionScoring::class);
    }

    public function validationRounds(): HasMany
    {
        return $this->hasMany(SurveyValidationRound::class);
    }

    public function validationRevisions(): HasMany
    {
        return $this->hasMany(SurveyValidationRevision::class);
    }

    public function readabilityRounds(): HasMany
    {
        return $this->hasMany(SurveyReadabilityRound::class);
    }

    public function readabilityRevisions(): HasMany
    {
        return $this->hasMany(SurveyReadabilityRevision::class);
    }

    public function respondents(): HasMany
    {
        return $this->hasMany(Respondent::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function analysisJobs(): HasMany
    {
        return $this->hasMany(AnalysisJob::class);
    }

    public function analysisResults(): HasMany
    {
        return $this->hasMany(AnalysisResult::class);
    }

    public function canReceiveResponses(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->is_public;
    }

    public function canTransitionTo(string $status): bool
    {
        return match ($this->status) {
            self::STATUS_DRAFT => in_array($status, [self::STATUS_PUBLISHED, self::STATUS_ARCHIVED], true),
            self::STATUS_PUBLISHED => $status === self::STATUS_CLOSED,
            self::STATUS_CLOSED => $status === self::STATUS_ARCHIVED,
            default => false,
        };
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        return $query->whereHas('project', function (Builder $projectQuery) use ($user): void {
            $projectQuery
                ->where('owner_id', $user->getKey())
                ->orWhereHas('activeMembers', fn (Builder $memberQuery) => $memberQuery->where('user_id', $user->getKey()));
        });
    }
}
