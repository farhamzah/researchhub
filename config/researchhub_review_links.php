<?php

use App\Models\ReviewLink;

return [
    'default_permissions' => [
        'view' => true,
        'comment' => false,
        'approve' => false,
        'request_revision' => false,
        'download' => false,
        'view_version_history' => false,
    ],

    'permission_keys' => [
        'view',
        'comment',
        'approve',
        'request_revision',
        'download',
        'view_version_history',
    ],

    'status_values' => ReviewLink::STATUSES,
];
