<?php

return [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/drive/callback'),
    'drive_scopes' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('GOOGLE_DRIVE_SCOPES', 'https://www.googleapis.com/auth/drive.file'))
    ))),
];
