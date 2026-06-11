<?php

use App\Models\DriveFolder;

return [
    'root_folder_name' => 'ResearchHub',

    'project_folders' => [
        [
            'type' => DriveFolder::TYPE_PROPOSAL,
            'name' => '01_Proposal',
        ],
        [
            'type' => DriveFolder::TYPE_BAB_I_II_III,
            'name' => '02_BAB_I_II_III',
        ],
        [
            'type' => DriveFolder::TYPE_BAB_IV_V,
            'name' => '03_BAB_IV_V',
        ],
        [
            'type' => DriveFolder::TYPE_ETHICS_AND_PERMITS,
            'name' => '04_Etik_dan_Izin',
        ],
        [
            'type' => DriveFolder::TYPE_INSTRUMENTS,
            'name' => '05_Instrumen',
        ],
        [
            'type' => DriveFolder::TYPE_SURVEY,
            'name' => '06_Survey',
        ],
        [
            'type' => DriveFolder::TYPE_DATA,
            'name' => '07_Data',
        ],
        [
            'type' => DriveFolder::TYPE_ANALYSIS,
            'name' => '08_Analisis',
        ],
        [
            'type' => DriveFolder::TYPE_PRESENTATION,
            'name' => '09_Presentasi',
        ],
        [
            'type' => DriveFolder::TYPE_PUBLICATION,
            'name' => '10_Publikasi',
        ],
        [
            'type' => DriveFolder::TYPE_APPENDIX,
            'name' => '11_Lampiran',
        ],
    ],
];
