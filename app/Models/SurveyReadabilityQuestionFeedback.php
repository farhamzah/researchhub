<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyReadabilityQuestionFeedback extends Model
{
    use HasFactory, HasUuids;

    public const ISSUE_UNCLEAR_WORDING = 'unclear_wording';

    public const ISSUE_AMBIGUOUS_MEANING = 'ambiguous_meaning';

    public const ISSUE_TOO_LONG = 'too_long';

    public const ISSUE_DIFFICULT_TERM = 'difficult_term';

    public const ISSUE_CONFUSING_OPTIONS = 'confusing_answer_options';

    public const ISSUE_NOT_RELEVANT = 'not_relevant_to_respondent';

    public const ISSUE_OTHER = 'other';

    public const ISSUE_TYPES = [
        self::ISSUE_UNCLEAR_WORDING,
        self::ISSUE_AMBIGUOUS_MEANING,
        self::ISSUE_TOO_LONG,
        self::ISSUE_DIFFICULT_TERM,
        self::ISSUE_CONFUSING_OPTIONS,
        self::ISSUE_NOT_RELEVANT,
        self::ISSUE_OTHER,
    ];

    public const ISSUE_LABELS = [
        self::ISSUE_UNCLEAR_WORDING => 'Unclear wording',
        self::ISSUE_AMBIGUOUS_MEANING => 'Ambiguous meaning',
        self::ISSUE_TOO_LONG => 'Too long',
        self::ISSUE_DIFFICULT_TERM => 'Difficult term',
        self::ISSUE_CONFUSING_OPTIONS => 'Confusing answer options',
        self::ISSUE_NOT_RELEVANT => 'Not relevant to respondent',
        self::ISSUE_OTHER => 'Other',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_readability_response_id',
        'survey_question_id',
        'survey_page_id',
        'issue_type',
        'comment',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyReadabilityResponse::class, 'survey_readability_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SurveyPage::class, 'survey_page_id');
    }
}
