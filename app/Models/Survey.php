<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
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

    public const INSTRUMENT_ANALYSIS_STUDENT = 'analysis_student_questionnaire';

    public const INSTRUMENT_ANALYSIS_LECTURER = 'analysis_lecturer_questionnaire';

    public const INSTRUMENT_PRACTITIONER_INTERVIEW = 'practitioner_interview_form';

    public const INSTRUMENT_OTHER = 'other';

    public const INSTRUMENT_TYPES = [
        self::INSTRUMENT_ANALYSIS_STUDENT,
        self::INSTRUMENT_ANALYSIS_LECTURER,
        self::INSTRUMENT_PRACTITIONER_INTERVIEW,
        self::INSTRUMENT_OTHER,
    ];

    public const INSTRUMENT_LABELS = [
        self::INSTRUMENT_ANALYSIS_STUDENT => 'Student Questionnaire',
        self::INSTRUMENT_ANALYSIS_LECTURER => 'Lecturer Questionnaire',
        self::INSTRUMENT_PRACTITIONER_INTERVIEW => 'Practitioner Interview Form',
        self::INSTRUMENT_OTHER => 'Other',
    ];

    public const ANALYSIS_GROUP_PHARMVR_ADDIE = 'pharmvr_addie_analysis';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'project_id',
        'created_by',
        'title',
        'slug',
        'description',
        'instrument_summary_override',
        'intro_title',
        'intro_text',
        'estimated_duration',
        'privacy_statement',
        'respondent_instruction',
        'consent_text',
        'require_consent_before_start',
        'intro_image_path',
        'intro_image_alt_text',
        'intro_image_caption',
        'intro_image_source_note',
        'schema',
        'status',
        'identity_mode',
        'instrument_type',
        'parent_survey_id',
        'analysis_group_key',
        'is_public',
        'published_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'is_public' => 'boolean',
            'require_consent_before_start' => 'boolean',
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

    public function parentSurvey(): BelongsTo
    {
        return $this->belongsTo(Survey::class, 'parent_survey_id');
    }

    public function relatedAnalysisInstruments(): HasMany
    {
        return $this->hasMany(Survey::class, 'parent_survey_id');
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

    public function synthesisItems(): HasMany
    {
        return $this->hasMany(AnalysisSynthesisItem::class);
    }

    public function analysisCollectionTargets(): HasMany
    {
        return $this->hasMany(AnalysisCollectionTarget::class);
    }

    public function analysisDocumentPackage(): HasOne
    {
        return $this->hasOne(AnalysisDocumentPackage::class);
    }

    public function analysisPreflightReviews(): HasMany
    {
        return $this->hasMany(AnalysisPreflightReview::class);
    }

    public function analysisPilotRuns(): HasMany
    {
        return $this->hasMany(AnalysisPilotRun::class);
    }

    public function getIntroImageUrlAttribute(): ?string
    {
        if (blank($this->intro_image_path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($this->intro_image_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->intro_image_path);
    }

    public function introImageUrl(): ?string
    {
        return $this->intro_image_url;
    }

    public function distributionBatches(): HasMany
    {
        return $this->hasMany(SurveyDistributionBatch::class);
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
