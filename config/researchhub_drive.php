<?php

use App\Models\DriveFolder;

return [
    'root_folder_name' => 'MyRiset',

    'global_folders' => [
        [
            'type' => DriveFolder::TYPE_PROJECTS_ROOT,
            'name' => 'Projects',
        ],
        [
            'type' => DriveFolder::TYPE_TEMPLATES,
            'name' => 'Templates',
        ],
        [
            'type' => DriveFolder::TYPE_GLOBAL_REPORTS,
            'name' => 'Reports',
        ],
        [
            'type' => DriveFolder::TYPE_GLOBAL_EXPORTS,
            'name' => 'Exports',
        ],
    ],

    'project_folders' => [
        [
            'type' => DriveFolder::TYPE_DOCUMENTS,
            'name' => 'Documents',
        ],
        [
            'type' => DriveFolder::TYPE_SURVEYS,
            'name' => 'Surveys',
        ],
        [
            'type' => DriveFolder::TYPE_VALIDATION,
            'name' => 'Validation',
        ],
        [
            'type' => DriveFolder::TYPE_SUPERVISION,
            'name' => 'Supervision',
        ],
        [
            'type' => DriveFolder::TYPE_REPORTS,
            'name' => 'Reports',
        ],
        [
            'type' => DriveFolder::TYPE_EXPORTS,
            'name' => 'Exports',
        ],
    ],
];
