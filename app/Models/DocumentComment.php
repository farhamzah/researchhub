<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentComment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const VISIBILITY_PROJECT = 'project';

    public const VISIBILITY_INTERNAL = 'internal';

    public const VISIBILITIES = [
        self::VISIBILITY_PROJECT,
        self::VISIBILITY_INTERNAL,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'document_id',
        'document_version_id',
        'user_id',
        'author_name',
        'author_email',
        'comment',
        'visibility',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
