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

class Document extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_REVISION_REQUIRED = 'revision_required';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_FINAL = 'final';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_REVISION_REQUIRED,
        self::STATUS_APPROVED,
        self::STATUS_FINAL,
        self::STATUS_ARCHIVED,
    ];

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_PROJECT = 'project';

    public const VISIBILITY_REVIEW_LINK = 'review_link';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITIES = [
        self::VISIBILITY_PRIVATE,
        self::VISIBILITY_PROJECT,
        self::VISIBILITY_REVIEW_LINK,
        self::VISIBILITY_PUBLIC,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'project_id',
        'category_id',
        'owner_id',
        'current_version_id',
        'title',
        'slug',
        'description',
        'status',
        'visibility',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Document $document): void {
            if (blank($document->slug)) {
                $document->slug = Str::slug($document->title);
            }

            if (blank($document->status)) {
                $document->status = self::STATUS_DRAFT;
            }

            if (blank($document->visibility)) {
                $document->visibility = self::VISIBILITY_PRIVATE;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'project_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DocumentComment::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class);
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
