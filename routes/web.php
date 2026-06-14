<?php

use App\Http\Controllers\AdminAnalysisExportController;
use App\Http\Controllers\AdminProjectJourneyController;
use App\Http\Controllers\AdminProjectSupervisionController;
use App\Http\Controllers\AdminProjectTemplateController;
use App\Http\Controllers\AdminProjectTimelineController;
use App\Http\Controllers\AdminProjectValidatorController;
use App\Http\Controllers\AdminSurveyAnalysisController;
use App\Http\Controllers\AdminSurveyBuilderController;
use App\Http\Controllers\AdminSurveyResponseController;
use App\Http\Controllers\AdminSurveyResponseExportController;
use App\Http\Controllers\AdminSurveyScoringController;
use App\Http\Controllers\AdminSurveyValidationController;
use App\Http\Controllers\AdminSurveyValidationResultController;
use App\Http\Controllers\PublicSupervisionReviewController;
use App\Http\Controllers\PublicSurveyController;
use App\Http\Controllers\PublicSurveyValidationController;
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
    Route::get('/google/drive/callback', [GoogleDriveOAuthController::class, 'callback'])
        ->name('drive.google.callback.alias');
    Route::post('/settings/drive/google/disconnect', [GoogleDriveOAuthController::class, 'disconnect'])
        ->name('drive.google.disconnect');
    Route::post('/settings/drive/google/bootstrap-folders', [GoogleDriveOAuthController::class, 'bootstrapFolders'])
        ->name('drive.google.bootstrap-folders');

    Route::get('/admin/documents/{document}/review-links', [AdminDocumentReviewLinkController::class, 'index'])
        ->name('admin.documents.review-links.index');
    Route::post('/admin/documents/{document}/review-links', [AdminDocumentReviewLinkController::class, 'store'])
        ->name('admin.documents.review-links.store');
    Route::post('/admin/documents/{document}/review-links/{reviewLink}/revoke', [AdminDocumentReviewLinkController::class, 'revoke'])
        ->name('admin.documents.review-links.revoke');

    Route::get('/admin/projects/{researchProject}/timeline', [AdminProjectTimelineController::class, 'index'])
        ->name('admin.projects.timeline.index');

    Route::get('/admin/projects/templates', [AdminProjectTemplateController::class, 'index'])
        ->name('admin.projects.templates.index');
    Route::get('/admin/projects/templates/{template}', [AdminProjectTemplateController::class, 'show'])
        ->name('admin.projects.templates.show');
    Route::post('/admin/projects/templates/{template}', [AdminProjectTemplateController::class, 'store'])
        ->name('admin.projects.templates.store');

    Route::get('/admin/projects/{researchProject}/journey', [AdminProjectJourneyController::class, 'show'])
        ->name('admin.projects.journey.show');
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

    Route::get('/admin/projects/{researchProject}/supervision', [AdminProjectSupervisionController::class, 'index'])
        ->name('admin.projects.supervision.index');
    Route::post('/admin/projects/{researchProject}/supervision/sessions', [AdminProjectSupervisionController::class, 'storeSession'])
        ->name('admin.projects.supervision.sessions.store');
    Route::put('/admin/projects/{researchProject}/supervision/sessions/{session}', [AdminProjectSupervisionController::class, 'updateSession'])
        ->name('admin.projects.supervision.sessions.update');
    Route::post('/admin/projects/{researchProject}/supervision/sessions/{session}/resources', [AdminProjectSupervisionController::class, 'storeResource'])
        ->name('admin.projects.supervision.resources.store');
    Route::put('/admin/projects/{researchProject}/supervision/sessions/{session}/resources/{resource}', [AdminProjectSupervisionController::class, 'updateResource'])
        ->name('admin.projects.supervision.resources.update');
    Route::delete('/admin/projects/{researchProject}/supervision/sessions/{session}/resources/{resource}', [AdminProjectSupervisionController::class, 'deleteResource'])
        ->name('admin.projects.supervision.resources.delete');
    Route::post('/admin/projects/{researchProject}/supervision/sessions/{session}/follow-ups', [AdminProjectSupervisionController::class, 'storeFollowUp'])
        ->name('admin.projects.supervision.follow-ups.store');
    Route::put('/admin/projects/{researchProject}/supervision/sessions/{session}/follow-ups/{followUp}', [AdminProjectSupervisionController::class, 'updateFollowUp'])
        ->name('admin.projects.supervision.follow-ups.update');
    Route::delete('/admin/projects/{researchProject}/supervision/sessions/{session}/follow-ups/{followUp}', [AdminProjectSupervisionController::class, 'deleteFollowUp'])
        ->name('admin.projects.supervision.follow-ups.delete');
    Route::post('/admin/projects/{researchProject}/supervision/sessions/{session}/links', [AdminProjectSupervisionController::class, 'generateLink'])
        ->name('admin.projects.supervision.links.generate');
    Route::post('/admin/projects/{researchProject}/supervision/links/{reviewLink}/revoke', [AdminProjectSupervisionController::class, 'revokeLink'])
        ->name('admin.projects.supervision.links.revoke');

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

    Route::get('/admin/surveys/{survey}/validation', [AdminSurveyValidationController::class, 'index'])
        ->name('admin.surveys.validation.index');
    Route::post('/admin/surveys/{survey}/validation/rounds', [AdminSurveyValidationController::class, 'storeRound'])
        ->name('admin.surveys.validation.rounds.store');
    Route::put('/admin/surveys/{survey}/validation/rounds/{round}', [AdminSurveyValidationController::class, 'updateRound'])
        ->name('admin.surveys.validation.rounds.update');
    Route::get('/admin/surveys/{survey}/validation/rounds/{round}/results', AdminSurveyValidationResultController::class)
        ->name('admin.surveys.validation.results.show');
    Route::post('/admin/surveys/{survey}/validation/rounds/{round}/assignments', [AdminSurveyValidationController::class, 'storeAssignment'])
        ->name('admin.surveys.validation.assignments.store');
    Route::post('/admin/surveys/{survey}/validation/assignments/{assignment}/generate-link', [AdminSurveyValidationController::class, 'generateLink'])
        ->name('admin.surveys.validation.assignments.generate-link');
    Route::post('/admin/surveys/{survey}/validation/assignments/{assignment}/revoke-link', [AdminSurveyValidationController::class, 'revokeLink'])
        ->name('admin.surveys.validation.assignments.revoke-link');
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
    Route::get('/validation/survey/{token}', [PublicSurveyValidationController::class, 'show'])
        ->name('validation.survey.show');
    Route::post('/validation/survey/{token}', [PublicSurveyValidationController::class, 'store'])
        ->name('validation.survey.store');
});

Route::middleware('throttle:review-links')->group(function (): void {
    Route::get('/supervision/review/{token}', [PublicSupervisionReviewController::class, 'show'])
        ->name('supervision.review.show');
    Route::post('/supervision/review/{token}', [PublicSupervisionReviewController::class, 'store'])
        ->name('supervision.review.store');
});
