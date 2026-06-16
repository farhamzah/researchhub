<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisSynthesisItem extends Model
{
    use HasFactory, HasUuids;

    public const SOURCE_STUDENT_SURVEY = 'student_survey';

    public const SOURCE_LECTURER_SURVEY = 'lecturer_survey';

    public const SOURCE_PRACTITIONER_INTERVIEW = 'practitioner_interview';

    public const SOURCE_EXPERT_VALIDATION = 'expert_validation';

    public const SOURCE_READABILITY_TEST = 'readability_test';

    public const SOURCE_DOCUMENT_REVIEW = 'document_review';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCES = [
        self::SOURCE_STUDENT_SURVEY,
        self::SOURCE_LECTURER_SURVEY,
        self::SOURCE_PRACTITIONER_INTERVIEW,
        self::SOURCE_EXPERT_VALIDATION,
        self::SOURCE_READABILITY_TEST,
        self::SOURCE_DOCUMENT_REVIEW,
        self::SOURCE_MANUAL,
    ];

    public const SOURCE_LABELS = [
        self::SOURCE_STUDENT_SURVEY => 'Student Survey',
        self::SOURCE_LECTURER_SURVEY => 'Lecturer Survey',
        self::SOURCE_PRACTITIONER_INTERVIEW => 'Practitioner Interview',
        self::SOURCE_EXPERT_VALIDATION => 'Expert Validation',
        self::SOURCE_READABILITY_TEST => 'Readability Test',
        self::SOURCE_DOCUMENT_REVIEW => 'Document Review',
        self::SOURCE_MANUAL => 'Manual',
    ];

    public const THEME_LEARNING_PROBLEM = 'learning_problem';

    public const THEME_CPOB_CONTENT_NEED = 'cpob_content_need';

    public const THEME_VR_MEDIA_NEED = 'vr_media_need';

    public const THEME_SCENE_PRIORITY = 'scene_priority';

    public const THEME_FEATURE_PRIORITY = 'feature_priority';

    public const THEME_TECHNOLOGY_READINESS = 'technology_readiness';

    public const THEME_ASSESSMENT_NEED = 'assessment_need';

    public const THEME_USABILITY_READABILITY = 'usability_readability';

    public const THEME_EXPERT_REVISION = 'expert_revision';

    public const THEME_DEVELOPMENT_RISK = 'development_risk';

    public const THEME_OTHER = 'other';

    public const THEMES = [
        self::THEME_LEARNING_PROBLEM,
        self::THEME_CPOB_CONTENT_NEED,
        self::THEME_VR_MEDIA_NEED,
        self::THEME_SCENE_PRIORITY,
        self::THEME_FEATURE_PRIORITY,
        self::THEME_TECHNOLOGY_READINESS,
        self::THEME_ASSESSMENT_NEED,
        self::THEME_USABILITY_READABILITY,
        self::THEME_EXPERT_REVISION,
        self::THEME_DEVELOPMENT_RISK,
        self::THEME_OTHER,
    ];

    public const THEME_LABELS = [
        self::THEME_LEARNING_PROBLEM => 'Learning Problem',
        self::THEME_CPOB_CONTENT_NEED => 'CPOB/GMP Content Need',
        self::THEME_VR_MEDIA_NEED => 'VR Media Need',
        self::THEME_SCENE_PRIORITY => 'Scene Priority',
        self::THEME_FEATURE_PRIORITY => 'Feature Priority',
        self::THEME_TECHNOLOGY_READINESS => 'Technology Readiness',
        self::THEME_ASSESSMENT_NEED => 'Assessment Need',
        self::THEME_USABILITY_READABILITY => 'Usability/Readability',
        self::THEME_EXPERT_REVISION => 'Expert Revision',
        self::THEME_DEVELOPMENT_RISK => 'Development Risk',
        self::THEME_OTHER => 'Other',
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_CRITICAL,
    ];

    public const PRIORITY_LABELS = [
        self::PRIORITY_LOW => 'Low',
        self::PRIORITY_MEDIUM => 'Medium',
        self::PRIORITY_HIGH => 'High',
        self::PRIORITY_CRITICAL => 'Critical',
    ];

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_IN_DESIGN = 'in_design';

    public const STATUS_IN_DEVELOPMENT = 'in_development';

    public const STATUS_DEFERRED = 'deferred';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PROPOSED,
        self::STATUS_ACCEPTED,
        self::STATUS_IN_DESIGN,
        self::STATUS_IN_DEVELOPMENT,
        self::STATUS_DEFERRED,
        self::STATUS_REJECTED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PROPOSED => 'Proposed',
        self::STATUS_ACCEPTED => 'Accepted',
        self::STATUS_IN_DESIGN => 'In Design',
        self::STATUS_IN_DEVELOPMENT => 'In Development',
        self::STATUS_DEFERRED => 'Deferred',
        self::STATUS_REJECTED => 'Rejected',
    ];

    public const MODULE_OPTIONS = [
        'Lobby',
        'Training Room',
        'Hygiene',
        'Gowning',
        'Airlock',
        'Production Corridor',
        'Weighing',
        'Granulation',
        'Tabletting',
        'Coating',
        'Packaging',
        'QC Lab',
        'QA Office',
        'Warehouse',
        'Assessment',
        'Dashboard',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_id',
        'project_id',
        'source_type',
        'source_label',
        'theme',
        'finding',
        'evidence_summary',
        'evidence_metric',
        'priority_level',
        'design_implication',
        'development_decision',
        'mapped_module',
        'status',
        'researcher_note',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'project_id');
    }
}
