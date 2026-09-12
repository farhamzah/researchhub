<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SurveyWorkflowTransition extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['survey_id', 'actor_id', 'actor_label', 'from_state', 'to_state', 'instrument_version', 'comment'];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Workflow transitions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Workflow transitions are immutable.'));
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
