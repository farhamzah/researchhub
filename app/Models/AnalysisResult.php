<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalysisResult extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'analysis_job_id',
        'project_id',
        'survey_id',
        'type',
        'title',
        'summary',
        'result_payload',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'result_payload' => 'array',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AnalysisJob::class, 'analysis_job_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'project_id');
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(AnalysisTable::class);
    }

    public function narratives(): HasMany
    {
        return $this->hasMany(AnalysisNarrative::class);
    }
}
