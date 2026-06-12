<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResearchLink extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const CATEGORY_JOURNAL = 'journal';

    public const CATEGORY_CONFERENCE = 'conference';

    public const CATEGORY_REGULATION = 'regulation';

    public const CATEGORY_REFERENCE = 'reference';

    public const CATEGORY_DATASET = 'dataset';

    public const CATEGORY_REPOSITORY = 'repository';

    public const CATEGORY_GOOGLE_DRIVE = 'google_drive';

    public const CATEGORY_OJS = 'ojs';

    public const CATEGORY_ETHICS = 'ethics';

    public const CATEGORY_STATISTICS = 'statistics';

    public const CATEGORY_LEARNING_RESOURCE = 'learning_resource';

    public const CATEGORY_METHODOLOGY = 'methodology';

    public const CATEGORY_AI_TOOL = 'ai_tool';

    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_JOURNAL,
        self::CATEGORY_CONFERENCE,
        self::CATEGORY_REGULATION,
        self::CATEGORY_REFERENCE,
        self::CATEGORY_DATASET,
        self::CATEGORY_REPOSITORY,
        self::CATEGORY_GOOGLE_DRIVE,
        self::CATEGORY_OJS,
        self::CATEGORY_ETHICS,
        self::CATEGORY_STATISTICS,
        self::CATEGORY_LEARNING_RESOURCE,
        self::CATEGORY_METHODOLOGY,
        self::CATEGORY_AI_TOOL,
        self::CATEGORY_OTHER,
    ];

    public const CATEGORY_LABELS = [
        self::CATEGORY_JOURNAL => 'Journal',
        self::CATEGORY_CONFERENCE => 'Conference',
        self::CATEGORY_REGULATION => 'Regulation',
        self::CATEGORY_REFERENCE => 'Reference',
        self::CATEGORY_DATASET => 'Dataset',
        self::CATEGORY_REPOSITORY => 'Repository',
        self::CATEGORY_GOOGLE_DRIVE => 'Google Drive',
        self::CATEGORY_OJS => 'OJS',
        self::CATEGORY_ETHICS => 'Ethics',
        self::CATEGORY_STATISTICS => 'Statistics',
        self::CATEGORY_LEARNING_RESOURCE => 'Learning Resource',
        self::CATEGORY_METHODOLOGY => 'Methodology',
        self::CATEGORY_AI_TOOL => 'AI Tool',
        self::CATEGORY_OTHER => 'Other',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'research_project_id',
        'created_by',
        'updated_by',
        'title',
        'url',
        'description',
        'category',
        'thumbnail_url',
        'favicon_url',
        'is_pinned',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'research_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->where(function (Builder $globalQuery) use ($user): void {
                    $globalQuery
                        ->whereNull('research_project_id')
                        ->where('created_by', $user->getKey());
                })
                ->orWhereHas('project', function (Builder $projectQuery) use ($user): void {
                    $projectQuery
                        ->where('owner_id', $user->getKey())
                        ->orWhereHas('activeMembers', fn (Builder $memberQuery) => $memberQuery->where('user_id', $user->getKey()));
                });
        });
    }
}
