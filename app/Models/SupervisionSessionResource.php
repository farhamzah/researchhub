<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupervisionSessionResource extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_RESEARCH_LINK = 'research_link';

    public const TYPE_TIMELINE_TASK = 'timeline_task';

    public const TYPE_SURVEY = 'survey';

    public const TYPE_VALIDATION_ROUND = 'validation_round';

    public const TYPE_MANUAL_URL = 'manual_url';

    public const TYPE_MANUAL_NOTE = 'manual_note';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_DOCUMENT,
        self::TYPE_RESEARCH_LINK,
        self::TYPE_TIMELINE_TASK,
        self::TYPE_SURVEY,
        self::TYPE_VALIDATION_ROUND,
        self::TYPE_MANUAL_URL,
        self::TYPE_MANUAL_NOTE,
        self::TYPE_OTHER,
    ];

    public const TYPE_LABELS = [
        self::TYPE_DOCUMENT => 'Document',
        self::TYPE_RESEARCH_LINK => 'Research Link',
        self::TYPE_TIMELINE_TASK => 'Timeline Task',
        self::TYPE_SURVEY => 'Survey',
        self::TYPE_VALIDATION_ROUND => 'Validation Round',
        self::TYPE_MANUAL_URL => 'Manual URL',
        self::TYPE_MANUAL_NOTE => 'Manual Note',
        self::TYPE_OTHER => 'Other',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'supervision_session_id',
        'created_by',
        'resource_type',
        'resource_id',
        'title',
        'url',
        'description',
        'notes',
        'sort_order',
        'is_visible_to_supervisor',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_visible_to_supervisor' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SupervisionSession::class, 'supervision_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->resource_type] ?? 'Resource';
    }

    public function displayTitle(): string
    {
        if (filled($this->title)) {
            return (string) $this->title;
        }

        return match ($this->resource_type) {
            self::TYPE_DOCUMENT => Document::query()->whereKey($this->resource_id)->value('title') ?: 'Document',
            self::TYPE_RESEARCH_LINK => ResearchLink::query()->whereKey($this->resource_id)->value('title') ?: 'Research Link',
            self::TYPE_TIMELINE_TASK => ProjectTimelineTask::query()->whereKey($this->resource_id)->value('title') ?: 'Timeline Task',
            self::TYPE_SURVEY => Survey::query()->whereKey($this->resource_id)->value('title') ?: 'Survey',
            self::TYPE_VALIDATION_ROUND => SurveyValidationRound::query()->whereKey($this->resource_id)->value('title') ?: 'Validation Round',
            default => $this->typeLabel(),
        };
    }

    public function safePublicUrl(): ?string
    {
        if ($this->resource_type === self::TYPE_MANUAL_URL) {
            return $this->isSafeUrl($this->url) ? $this->url : null;
        }

        if ($this->resource_type === self::TYPE_RESEARCH_LINK && $this->resource_id !== null) {
            $url = ResearchLink::query()->whereKey($this->resource_id)->value('url');

            return $this->isSafeUrl($url) ? $url : null;
        }

        return null;
    }

    private function isSafeUrl(?string $url): bool
    {
        if (blank($url)) {
            return false;
        }

        $parts = parse_url((string) $url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return in_array($scheme, ['http', 'https'], true)
            && filled($parts['host'] ?? null)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
