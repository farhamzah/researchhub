<?php

return [
    'statuses' => [
        'draft',
        'published',
        'closed',
        'archived',
    ],

    'identity_modes' => [
        'full_identity',
        'hidden_identity',
        'anonymous',
        'pseudonym',
    ],

    'default_status' => 'draft',
    'default_identity_mode' => 'hidden_identity',

    'question_types' => [
        'short_text',
        'long_text',
        'single_choice',
        'multiple_choice',
        'likert',
        'likert_matrix',
        'number',
        'date',
        'consent',
        'hidden',
    ],

    'default_likert_scale' => [1, 2, 3, 4, 5],

    'reserved_hidden_keys' => [
        'id',
        'project_id',
        'survey_id',
        'respondent_id',
        'response_token_hash',
        'status',
        'submitted_at',
        'ip_address',
        'user_agent',
    ],
];
