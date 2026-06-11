<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentVersion extends Model
{
    use HasFactory, HasUuids;

    public const STORAGE_STATUS_PENDING = 'pending';

    public const STORAGE_STATUS_STORED = 'stored';

    public const STORAGE_STATUS_FAILED = 'failed';

    public const STORAGE_STATUS_EXTERNAL = 'external';

    public const STORAGE_STATUS_FAKE = 'fake';

    public const STORAGE_STATUSES = [
        self::STORAGE_STATUS_PENDING,
        self::STORAGE_STATUS_STORED,
        self::STORAGE_STATUS_FAILED,
        self::STORAGE_STATUS_EXTERNAL,
        self::STORAGE_STATUS_FAKE,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'document_id',
        'version_number',
        'uploaded_by',
        'drive_file_id',
        'drive_folder_id',
        'file_name',
        'original_file_name',
        'mime_type',
        'file_extension',
        'file_size',
        'checksum',
        'web_view_link',
        'web_download_link',
        'storage_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewLinks(): HasMany
    {
        return $this->hasMany(ReviewLink::class, 'document_version_id');
    }
}
