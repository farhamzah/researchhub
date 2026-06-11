<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriveFolder extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_PROJECT_ROOT = 'project_root';

    public const TYPES = [
        self::TYPE_PROJECT_ROOT,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'project_id',
        'user_id',
        'folder_type',
        'drive_folder_id',
        'name',
        'path',
        'web_view_link',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
