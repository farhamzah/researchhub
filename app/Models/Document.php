<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
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

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_UNDER_REVIEW => 'Under Review',
        self::STATUS_REVISION_REQUIRED => 'Revision Needed',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_FINAL => 'Final',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_SUBMITTED => 'info',
        self::STATUS_UNDER_REVIEW => 'warning',
        self::STATUS_REVISION_REQUIRED => 'danger',
        self::STATUS_APPROVED => 'success',
        self::STATUS_FINAL => 'success',
        self::STATUS_ARCHIVED => 'gray',
    ];

    public const TYPE_PROPOSAL = 'proposal';

    public const TYPE_CHAPTER_1 = 'chapter_1';

    public const TYPE_CHAPTER_2 = 'chapter_2';

    public const TYPE_CHAPTER_3 = 'chapter_3';

    public const TYPE_INSTRUMENT = 'instrument';

    public const TYPE_VALIDATION_REPORT = 'validation_report';

    public const TYPE_ANALYSIS_REPORT = 'analysis_report';

    public const TYPE_SUPERVISION_LOG = 'supervision_log';

    public const TYPE_JOURNAL_ARTICLE = 'journal_article';

    public const TYPE_PUBLICATION_DRAFT = 'publication_draft';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_PROPOSAL,
        self::TYPE_CHAPTER_1,
        self::TYPE_CHAPTER_2,
        self::TYPE_CHAPTER_3,
        self::TYPE_INSTRUMENT,
        self::TYPE_VALIDATION_REPORT,
        self::TYPE_ANALYSIS_REPORT,
        self::TYPE_SUPERVISION_LOG,
        self::TYPE_JOURNAL_ARTICLE,
        self::TYPE_PUBLICATION_DRAFT,
        self::TYPE_OTHER,
    ];

    public const TYPE_LABELS = [
        self::TYPE_PROPOSAL => 'Proposal',
        self::TYPE_CHAPTER_1 => 'BAB I',
        self::TYPE_CHAPTER_2 => 'BAB II',
        self::TYPE_CHAPTER_3 => 'BAB III',
        self::TYPE_INSTRUMENT => 'Instrument',
        self::TYPE_VALIDATION_REPORT => 'Validation Report',
        self::TYPE_ANALYSIS_REPORT => 'Analysis Report',
        self::TYPE_SUPERVISION_LOG => 'Supervision Log',
        self::TYPE_JOURNAL_ARTICLE => 'Journal Article',
        self::TYPE_PUBLICATION_DRAFT => 'Publication Draft',
        self::TYPE_OTHER => 'Other',
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
        'document_type',
        'version_label',
        'version_number',
        'is_current',
        'reviewer_name',
        'reviewed_at',
        'revision_due_date',
        'next_action',
        'revision_summary',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'reviewed_at' => 'datetime',
            'revision_due_date' => 'date',
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

            if (blank($document->version_number)) {
                $document->version_number = 1;
            }

            if ($document->is_current === null) {
                $document->is_current = true;
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

    public function reviewLinks(): HasMany
    {
        return $this->hasMany(ReviewLink::class);
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

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? Str::headline((string) $this->status);
    }

    public function documentTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->document_type] ?? Str::headline((string) ($this->document_type ?: $this->category?->name ?: 'Document'));
    }

    public function versionDisplay(): string
    {
        if (filled($this->version_label)) {
            return (string) $this->version_label;
        }

        $versionNumber = (int) ($this->version_number ?: $this->currentVersion?->version_number ?: 1);

        return 'v'.str_pad((string) $versionNumber, 2, '0', STR_PAD_LEFT);
    }

    public function needsRevision(): bool
    {
        return $this->status === self::STATUS_REVISION_REQUIRED;
    }

    public function isRevisionOverdue(?Carbon $today = null): bool
    {
        if (! $this->revision_due_date || in_array($this->status, [self::STATUS_APPROVED, self::STATUS_FINAL, self::STATUS_ARCHIVED], true)) {
            return false;
        }

        return $this->revision_due_date->isBefore($today ?? today());
    }
}
