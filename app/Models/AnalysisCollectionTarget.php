<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisCollectionTarget extends Model
{
    use HasFactory, HasUuids;

    public const SOURCE_STUDENT_QUESTIONNAIRE = 'student_questionnaire';

    public const SOURCE_LECTURER_QUESTIONNAIRE = 'lecturer_questionnaire';

    public const SOURCE_PRACTITIONER_INTERVIEW = 'practitioner_interview';

    public const SOURCE_EXPERT_VALIDATION = 'expert_validation';

    public const SOURCE_READABILITY_TEST = 'readability_test';

    public const SOURCE_SYNTHESIS_MATRIX = 'synthesis_matrix';

    public const SOURCES = [
        self::SOURCE_STUDENT_QUESTIONNAIRE,
        self::SOURCE_LECTURER_QUESTIONNAIRE,
        self::SOURCE_PRACTITIONER_INTERVIEW,
        self::SOURCE_EXPERT_VALIDATION,
        self::SOURCE_READABILITY_TEST,
        self::SOURCE_SYNTHESIS_MATRIX,
    ];

    public const SOURCE_LABELS = [
        self::SOURCE_STUDENT_QUESTIONNAIRE => 'Student Questionnaire',
        self::SOURCE_LECTURER_QUESTIONNAIRE => 'Lecturer Questionnaire',
        self::SOURCE_PRACTITIONER_INTERVIEW => 'Practitioner Interview Form',
        self::SOURCE_EXPERT_VALIDATION => 'Expert Validation',
        self::SOURCE_READABILITY_TEST => 'Readability Test',
        self::SOURCE_SYNTHESIS_MATRIX => 'Synthesis Matrix',
    ];

    public const DEFAULTS = [
        self::SOURCE_STUDENT_QUESTIONNAIRE => ['minimum' => 30, 'target' => 50],
        self::SOURCE_LECTURER_QUESTIONNAIRE => ['minimum' => 5, 'target' => 10],
        self::SOURCE_PRACTITIONER_INTERVIEW => ['minimum' => 3, 'target' => 5],
        self::SOURCE_EXPERT_VALIDATION => ['minimum' => 3, 'target' => 5],
        self::SOURCE_READABILITY_TEST => ['minimum' => 5, 'target' => 10],
        self::SOURCE_SYNTHESIS_MATRIX => ['minimum' => 5, 'target' => 10],
    ];

    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_COLLECTING = 'collecting';

    public const STATUS_MINIMUM_MET = 'minimum_met';

    public const STATUS_TARGET_MET = 'target_met';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    public const STATUS_LABELS = [
        self::STATUS_NOT_STARTED => 'Not Started',
        self::STATUS_COLLECTING => 'Collecting',
        self::STATUS_MINIMUM_MET => 'Minimum Met',
        self::STATUS_TARGET_MET => 'Target Met',
        self::STATUS_OVERDUE => 'Overdue',
        self::STATUS_NOT_APPLICABLE => 'Not Applicable',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_id',
        'project_id',
        'target_survey_id',
        'source_type',
        'label',
        'minimum_count',
        'target_count',
        'due_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'minimum_count' => 'integer',
            'target_count' => 'integer',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
