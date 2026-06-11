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
    ],

    'docx_export' => [
        'enabled' => false,
        'deferred_reason' => 'phpoffice/phpword is not installed; DOCX export is deferred until report generation dependencies are approved.',
    ],
];
