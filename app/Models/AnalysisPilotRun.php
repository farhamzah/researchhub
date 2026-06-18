<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalysisPilotRun extends Model
{
    use HasFactory, HasUuids;

    public const AUDIENCE_STUDENT = 'student';

    public const AUDIENCE_LECTURER = 'lecturer';

    public const AUDIENCE_PRACTITIONER = 'practitioner';

    public const AUDIENCES = [
        self::AUDIENCE_STUDENT,
        self::AUDIENCE_LECTURER,
        self::AUDIENCE_PRACTITIONER,
    ];

    public const AUDIENCE_LABELS = [
        self::AUDIENCE_STUDENT => 'Student Questionnaire',
        self::AUDIENCE_LECTURER => 'Lecturer Questionnaire',
        self::AUDIENCE_PRACTITIONER => 'Practitioner Interview Form',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_PASSED = 'passed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVOKED = 'revoked';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_SUBMITTED,
        self::STATUS_PASSED,
        self::STATUS_FAILED,
        self::STATUS_REVOKED,
    ];

    public const REQUIRED_CHECKLIST_KEYS = [
        'intro_ok',
        'consent_ok',
        'questions_ok',
        'required_validation_ok',
        'submit_ok',
        'thank_you_ok',
        'excluded_from_analysis_ok',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_id',
        'project_id',
        'target_survey_id',
        'audience_type',
        'token_hash',
        'status',
        'checklist_json',
        'notes',
        'generated_by',
        'submitted_at',
        'passed_at',
        'revoked_at',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'checklist_json' => 'array',
            'submitted_at' => 'datetime',
            'passed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
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

    public function targetSurvey(): BelongsTo
    {
        return $this->belongsTo(Survey::class, 'target_survey_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class, 'pilot_run_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNotNull('token_hash')
            ->where('status', '!=', self::STATUS_REVOKED)
            ->where(function (Builder $dateQuery): void {
                $dateQuery->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isActive(): bool
    {
        return filled($this->token_hash)
            && $this->status !== self::STATUS_REVOKED
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function hasPassedChecklist(): bool
    {
        $checklist = $this->checklist_json ?? [];

        foreach (self::REQUIRED_CHECKLIST_KEYS as $key) {
            if (($checklist[$key] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }
}
