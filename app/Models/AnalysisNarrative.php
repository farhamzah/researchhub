<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisNarrative extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'analysis_result_id',
        'section',
        'language',
        'narrative',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(AnalysisResult::class, 'analysis_result_id');
    }
}
