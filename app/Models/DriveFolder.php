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

    public const TYPE_PROJECT_ROOT = 'project_root';

    public const TYPE_PROPOSAL = 'proposal';

    public const TYPE_BAB_I_II_III = 'bab_i_ii_iii';

    public const TYPE_BAB_IV_V = 'bab_iv_v';

    public const TYPE_ETHICS_AND_PERMITS = 'ethics_and_permits';

    public const TYPE_INSTRUMENTS = 'instruments';

    public const TYPE_SURVEY = 'survey';

    public const TYPE_DATA = 'data';

    public const TYPE_ANALYSIS = 'analysis';

    public const TYPE_PRESENTATION = 'presentation';

    public const TYPE_PUBLICATION = 'publication';

    public const TYPE_APPENDIX = 'appendix';

    public const TYPES = [
        self::TYPE_RESEARCHHUB_ROOT,
        self::TYPE_PROJECT_ROOT,
        self::TYPE_PROPOSAL,
        self::TYPE_BAB_I_II_III,
        self::TYPE_BAB_IV_V,
        self::TYPE_ETHICS_AND_PERMITS,
        self::TYPE_INSTRUMENTS,
        self::TYPE_SURVEY,
        self::TYPE_DATA,
        self::TYPE_ANALYSIS,
        self::TYPE_PRESENTATION,
        self::TYPE_PUBLICATION,
        self::TYPE_APPENDIX,
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
