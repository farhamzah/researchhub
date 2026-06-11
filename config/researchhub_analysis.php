<?php

return [
    'types' => [
        'survey_descriptive',
    ],

    'statuses' => [
        'pending',
        'running',
        'completed',
        'failed',
    ],

    'sample_answer_limit' => 5,
    'sample_answer_max_length' => 140,

    'export_formats' => [
        'csv',
        'markdown',
        'docx',
    ],

    'docx_export' => [
        'enabled' => true,
        'dependency' => 'phpoffice/phpword',
        'required_extensions' => [
            'zip',
            'xml',
            'xmlwriter',
        ],
    ],

    'docx' => [
        'default_font' => 'Calibri',
        'default_font_size' => 11,
        'heading_font_size' => 14,
        'table_font_size' => 9,
        'margin_top' => 1440,
        'margin_right' => 1080,
        'margin_bottom' => 1440,
        'margin_left' => 1080,
        'include_footer' => true,
        'include_verification_checklist' => true,
    ],
];
