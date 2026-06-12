<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriveFolder extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_RESEARCHHUB_ROOT = 'researchhub_root';

    public const TYPE_PROJECTS_ROOT = 'projects_root';

    public const TYPE_TEMPLATES = 'templates';

    public const TYPE_GLOBAL_REPORTS = 'global_reports';

    public const TYPE_GLOBAL_EXPORTS = 'global_exports';

    public const TYPE_PROJECT_ROOT = 'project_root';

    public const TYPE_DOCUMENTS = 'documents';

    public const TYPE_SURVEYS = 'surveys';

    public const TYPE_VALIDATION = 'validation';

    public const TYPE_SUPERVISION = 'supervision';

    public const TYPE_REPORTS = 'reports';

    public const TYPE_EXPORTS = 'exports';

    public const TYPES = [
        self::TYPE_RESEARCHHUB_ROOT,
        self::TYPE_PROJECTS_ROOT,
        self::TYPE_TEMPLATES,
        self::TYPE_GLOBAL_REPORTS,
        self::TYPE_GLOBAL_EXPORTS,
        self::TYPE_PROJECT_ROOT,
        self::TYPE_DOCUMENTS,
        self::TYPE_SURVEYS,
        self::TYPE_VALIDATION,
        self::TYPE_SUPERVISION,
        self::TYPE_REPORTS,
        self::TYPE_EXPORTS,
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
