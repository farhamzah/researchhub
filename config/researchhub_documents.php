<?php

use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentVersion;

return [
    'allowed_extensions' => [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'csv',
        'ppt',
        'pptx',
        'txt',
        'rtf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'mp4',
        'mov',
        'zip',
    ],

    'allowed_mime_types' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/rtf',
        'text/rtf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
        'video/quicktime',
        'application/zip',
    ],

    'max_upload_size_kb' => env('RESEARCHHUB_DOCUMENT_MAX_UPLOAD_SIZE_KB', 51200),

    'status_values' => Document::STATUSES,

    'visibility_values' => Document::VISIBILITIES,

    'storage_status_values' => DocumentVersion::STORAGE_STATUSES,

    'approval_decision_values' => DocumentApproval::DECISIONS,

    'default_categories' => [
        'Proposal',
        'BAB I',
        'BAB II',
        'BAB III',
        'BAB IV',
        'BAB V',
        'Etik',
        'Surat Izin',
        'Instrumen',
        'Validasi Ahli',
        'Artikel Referensi',
        'Data Mentah',
        'Data Bersih',
        'Hasil Analisis',
        'Laporan',
        'Presentasi',
        'Poster',
        'Publikasi Jurnal',
        'Bukti Kegiatan',
        'Foto/Video',
        'Lampiran',
    ],
];
