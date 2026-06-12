<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupervisionSession extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const MEETING_REGULAR_GUIDANCE = 'regular_guidance';

    public const MEETING_PROPOSAL_REVIEW = 'proposal_review';

    public const MEETING_CHAPTER_REVIEW = 'chapter_review';

    public const MEETING_INSTRUMENT_REVIEW = 'instrument_review';

    public const MEETING_RESULT_REVIEW = 'result_review';

    public const MEETING_ARTICLE_REVIEW = 'article_review';

    public const MEETING_REVISION_REVIEW = 'revision_review';

    public const MEETING_FINAL_REVIEW = 'final_review';

    public const MEETING_OTHER = 'other';

    public const MEETING_TYPES = [
        self::MEETING_REGULAR_GUIDANCE,
        self::MEETING_PROPOSAL_REVIEW,
        self::MEETING_CHAPTER_REVIEW,
        self::MEETING_INSTRUMENT_REVIEW,
        self::MEETING_RESULT_REVIEW,
        self::MEETING_ARTICLE_REVIEW,
        self::MEETING_REVISION_REVIEW,
        self::MEETING_FINAL_REVIEW,
        self::MEETING_OTHER,
    ];

    public const MEETING_TYPE_LABELS = [
        self::MEETING_REGULAR_GUIDANCE => 'Regular Guidance',
        self::MEETING_PROPOSAL_REVIEW => 'Proposal Review',
        self::MEETING_CHAPTER_REVIEW => 'Chapter Review',
        self::MEETING_INSTRUMENT_REVIEW => 'Instrument Review',
        self::MEETING_RESULT_REVIEW => 'Result Review',
        self::MEETING_ARTICLE_REVIEW => 'Article Review',
        self::MEETING_REVISION_REVIEW => 'Revision Review',
        self::MEETING_FINAL_REVIEW => 'Final Review',
        self::MEETING_OTHER => 'Other',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SHARED = 'shared';

    public const STATUS_OPENED = 'opened';

    public const STATUS_FEEDBACK_SUBMITTED = 'feedback_submitted';

    public const STATUS_REVISION_NEEDED = 'revision_needed';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SHARED,
        self::STATUS_OPENED,
        self::STATUS_FEEDBACK_SUBMITTED,
        self::STATUS_REVISION_NEEDED,
        self::STATUS_APPROVED,
        self::STATUS_CLOSED,
        self::STATUS_ARCHIVED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SHARED => 'Shared',
        self::STATUS_OPENED => 'Opened',
        self::STATUS_FEEDBACK_SUBMITTED => 'Feedback Submitted',
        self::STATUS_REVISION_NEEDED => 'Revision Needed',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_CLOSED => 'Closed',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'research_project_id',
        'created_by',
        'title',
        'meeting_type',
        'status',
        'agenda',
        'progress_report',
        'questions',
        'requested_feedback',
        'next_plan',
        'notes',
        'target_date',
        'submitted_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupervisionSession $session): void {
            if (blank($session->status)) {
                $session->status = self::STATUS_DRAFT;
            }

            if (blank($session->meeting_type)) {
                $session->meeting_type = self::MEETING_REGULAR_GUIDANCE;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'research_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewLinks(): HasMany
    {
        return $this->hasMany(SupervisionReviewLink::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(SupervisionFeedback::class);
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

    public function copyReadySummary(): string
    {
        $latestFeedback = $this->feedback()->latest()->first();

        return collect([
            'Topik bimbingan: '.$this->title,
            'Progress yang dilaporkan: '.($this->progress_report ?: '-'),
            'Pertanyaan untuk pembimbing: '.($this->questions ?: '-'),
            'Masukan pembimbing: '.($latestFeedback?->general_feedback ?: '-'),
            'Tindak lanjut: '.($latestFeedback?->recommended_next_steps ?: ($this->next_plan ?: '-')),
            'Status: '.(self::STATUS_LABELS[$this->status] ?? $this->status),
        ])->join(PHP_EOL);
    }
}
