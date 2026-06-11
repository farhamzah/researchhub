<?php

use App\Modules\DriveIntegration\Controllers\GoogleDriveOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/settings/drive/google', [GoogleDriveOAuthController::class, 'status'])
        ->name('drive.google.status');
    Route::get('/settings/drive/google/redirect', [GoogleDriveOAuthController::class, 'redirect'])
        ->name('drive.google.redirect');
    Route::get('/auth/google/drive/callback', [GoogleDriveOAuthController::class, 'callback'])
        ->name('drive.google.callback');
    Route::post('/settings/drive/google/disconnect', [GoogleDriveOAuthController::class, 'disconnect'])
        ->name('drive.google.disconnect');
});
