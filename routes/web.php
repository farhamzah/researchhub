<?php

use App\Http\Controllers\AdminAnalysisExportController;
use App\Http\Controllers\AdminProjectTimelineController;
use App\Http\Controllers\AdminProjectValidatorController;
use App\Http\Controllers\AdminSurveyAnalysisController;
use App\Http\Controllers\AdminSurveyBuilderController;
use App\Http\Controllers\AdminSurveyResponseController;
use App\Http\Controllers\AdminSurveyResponseExportController;
use App\Http\Controllers\AdminSurveyScoringController;
use App\Http\Controllers\PublicSurveyController;
use App\Modules\DriveIntegration\Controllers\GoogleDriveOAuthController;
use App\Modules\ReviewLinks\Controllers\AdminDocumentReviewLinkController;
use App\Modules\ReviewLinks\Controllers\PublicReviewLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function (): void {
    Route::redirect('/admin/dashboard', '/admin')
        ->name('admin.dashboard.redirect');

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

    Route::get('/admin/projects/{researchProject}/timeline', [AdminProjectTimelineController::class, 'index'])
        ->name('admin.projects.timeline.index');
    Route::post('/admin/projects/{researchProject}/timeline/milestones', [AdminProjectTimelineController::class, 'storeMilestone'])
        ->name('admin.projects.timeline.milestones.store');
    Route::put('/admin/projects/{researchProject}/timeline/milestones/{milestone}', [AdminProjectTimelineController::class, 'updateMilestone'])
        ->name('admin.projects.timeline.milestones.update');
    Route::delete('/admin/projects/{researchProject}/timeline/milestones/{milestone}', [AdminProjectTimelineController::class, 'deleteMilestone'])
        ->name('admin.projects.timeline.milestones.delete');
    Route::post('/admin/projects/{researchProject}/timeline/tasks', [AdminProjectTimelineController::class, 'storeTask'])
        ->name('admin.projects.timeline.tasks.store');
    Route::put('/admin/projects/{researchProject}/timeline/tasks/{task}', [AdminProjectTimelineController::class, 'updateTask'])
        ->name('admin.projects.timeline.tasks.update');
    Route::delete('/admin/projects/{researchProject}/timeline/tasks/{task}', [AdminProjectTimelineController::class, 'deleteTask'])
        ->name('admin.projects.timeline.tasks.delete');

    Route::get('/admin/projects/{researchProject}/validators', [AdminProjectValidatorController::class, 'index'])
        ->name('admin.projects.validators.index');
    Route::post('/admin/projects/{researchProject}/validators', [AdminProjectValidatorController::class, 'store'])
        ->name('admin.projects.validators.store');
    Route::put('/admin/projects/{researchProject}/validators/{assignment}', [AdminProjectValidatorController::class, 'update'])
        ->name('admin.projects.validators.update');
    Route::delete('/admin/projects/{researchProject}/validators/{assignment}', [AdminProjectValidatorController::class, 'destroy'])
        ->name('admin.projects.validators.destroy');

    Route::get('/admin/surveys/{survey}/responses', [AdminSurveyResponseController::class, 'index'])
        ->name('admin.surveys.responses.index');
    Route::get('/admin/surveys/{survey}/responses/export', AdminSurveyResponseExportController::class)
        ->name('admin.surveys.responses.export');
    Route::get('/admin/surveys/{survey}/responses/{response}', [AdminSurveyResponseController::class, 'show'])
        ->name('admin.surveys.responses.show');

    Route::get('/admin/surveys/{survey}/analysis', [AdminSurveyAnalysisController::class, 'index'])
        ->name('admin.surveys.analysis.index');
    Route::post('/admin/surveys/{survey}/analysis', [AdminSurveyAnalysisController::class, 'run'])
        ->name('admin.surveys.analysis.run');
    Route::get('/admin/analysis/results/{analysisResult}', [AdminSurveyAnalysisController::class, 'show'])
        ->name('admin.analysis.results.show');
    Route::get('/admin/analysis/{analysisResult}/export/csv', [AdminAnalysisExportController::class, 'csv'])
        ->name('admin.analysis.export.csv');
    Route::get('/admin/analysis/{analysisResult}/export/markdown', [AdminAnalysisExportController::class, 'markdown'])
        ->name('admin.analysis.export.markdown');
    Route::get('/admin/analysis/{analysisResult}/export/docx', [AdminAnalysisExportController::class, 'docx'])
        ->name('admin.analysis.export.docx');

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

    Route::get('/admin/surveys/{survey}/scoring', [AdminSurveyScoringController::class, 'index'])
        ->name('admin.surveys.scoring.index');
    Route::post('/admin/surveys/{survey}/scoring/scales', [AdminSurveyScoringController::class, 'storeScale'])
        ->name('admin.surveys.scoring.scales.store');
    Route::put('/admin/surveys/{survey}/scoring/scales/{scale}', [AdminSurveyScoringController::class, 'updateScale'])
        ->name('admin.surveys.scoring.scales.update');
    Route::delete('/admin/surveys/{survey}/scoring/scales/{scale}', [AdminSurveyScoringController::class, 'deleteScale'])
        ->name('admin.surveys.scoring.scales.delete');
    Route::post('/admin/surveys/{survey}/scoring/indicators', [AdminSurveyScoringController::class, 'storeIndicator'])
        ->name('admin.surveys.scoring.indicators.store');
    Route::put('/admin/surveys/{survey}/scoring/indicators/{indicator}', [AdminSurveyScoringController::class, 'updateIndicator'])
        ->name('admin.surveys.scoring.indicators.update');
    Route::delete('/admin/surveys/{survey}/scoring/indicators/{indicator}', [AdminSurveyScoringController::class, 'deleteIndicator'])
        ->name('admin.surveys.scoring.indicators.delete');
    Route::put('/admin/surveys/{survey}/scoring/questions/{question}', [AdminSurveyScoringController::class, 'updateQuestionScoring'])
        ->name('admin.surveys.scoring.questions.update');
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
