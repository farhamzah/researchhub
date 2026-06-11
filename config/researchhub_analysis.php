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
];
