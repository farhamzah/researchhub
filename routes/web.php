<?php

use App\Http\Controllers\AdminSurveyBuilderController;
use App\Http\Controllers\AdminSurveyResponseController;
use App\Http\Controllers\PublicSurveyController;
use App\Modules\DriveIntegration\Controllers\GoogleDriveOAuthController;
use App\Modules\ReviewLinks\Controllers\AdminDocumentReviewLinkController;
use App\Modules\ReviewLinks\Controllers\PublicReviewLinkController;
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

    Route::get('/admin/documents/{document}/review-links', [AdminDocumentReviewLinkController::class, 'index'])
        ->name('admin.documents.review-links.index');
    Route::post('/admin/documents/{document}/review-links', [AdminDocumentReviewLinkController::class, 'store'])
        ->name('admin.documents.review-links.store');
    Route::post('/admin/documents/{document}/review-links/{reviewLink}/revoke', [AdminDocumentReviewLinkController::class, 'revoke'])
        ->name('admin.documents.review-links.revoke');

    Route::get('/admin/surveys/{survey}/responses', [AdminSurveyResponseController::class, 'index'])
        ->name('admin.surveys.responses.index');
    Route::get('/admin/surveys/{survey}/responses/{response}', [AdminSurveyResponseController::class, 'show'])
        ->name('admin.surveys.responses.show');

    Route::get('/admin/surveys/{survey}/builder', [AdminSurveyBuilderController::class, 'index'])
        ->name('admin.surveys.builder.index');
    Route::post('/admin/surveys/{survey}/builder/pages', [AdminSurveyBuilderController::class, 'storePage'])
        ->name('admin.surveys.builder.pages.store');
    Route::put('/admin/surveys/{survey}/builder/pages/{page}', [AdminSurveyBuilderController::class, 'updatePage'])
        ->name('admin.surveys.builder.pages.update');
    Route::delete('/admin/surveys/{survey}/builder/pages/{page}', [AdminSurveyBuilderController::class, 'deletePage'])
        ->name('admin.surveys.builder.pages.delete');
    Route::post('/admin/surveys/{survey}/builder/questions', [AdminSurveyBuilderController::class, 'storeQuestion'])
        ->name('admin.surveys.builder.questions.store');
    Route::put('/admin/surveys/{survey}/builder/questions/{question}', [AdminSurveyBuilderController::class, 'updateQuestion'])
        ->name('admin.surveys.builder.questions.update');
    Route::delete('/admin/surveys/{survey}/builder/questions/{question}', [AdminSurveyBuilderController::class, 'deleteQuestion'])
        ->name('admin.surveys.builder.questions.delete');
    Route::post('/admin/surveys/{survey}/builder/questions/{question}/duplicate', [AdminSurveyBuilderController::class, 'duplicateQuestion'])
        ->name('admin.surveys.builder.questions.duplicate');
});

Route::middleware('throttle:review-links')->group(function (): void {
    Route::get('/review/{token}', [PublicReviewLinkController::class, 'show'])
        ->name('review.show');
    Route::post('/review/{token}/comments', [PublicReviewLinkController::class, 'comment'])
        ->name('review.comments.store');
    Route::post('/review/{token}/decision', [PublicReviewLinkController::class, 'decision'])
        ->name('review.decision.store');
    Route::get('/review/{token}/download', [PublicReviewLinkController::class, 'download'])
        ->name('review.download');
});

Route::post('/review/{token}/password', [PublicReviewLinkController::class, 'password'])
    ->middleware('throttle:review-link-passwords')
    ->name('review.password');

Route::middleware('throttle:surveys')->group(function (): void {
    Route::get('/survey/{survey:slug}', [PublicSurveyController::class, 'show'])
        ->name('survey.show');
    Route::post('/survey/{survey:slug}/responses', [PublicSurveyController::class, 'store'])
        ->name('survey.responses.store');
});
