<?php

namespace App\Modules\SupervisorReviews\Actions;

use App\Models\Survey;
use App\Models\SurveySupervisorReviewer;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\SupervisorReviews\DTOs\SupervisorReviewLinkGenerationResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class GenerateSupervisorReviewLinkAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Survey $survey, SurveySupervisorReviewer $reviewer, ?Request $request = null): SupervisorReviewLinkGenerationResult
    {
        $reviewer->loadMissing('round');
        abort_unless($reviewer->round?->survey_id === $survey->getKey(), 404);
        Gate::forUser($user)->authorize('manageSupervisorReview', $survey);

        $rawToken = Str::random(64);

        $reviewer->forceFill([
            'token_hash' => SurveySupervisorReviewer::hashToken($rawToken),
            'token_created_at' => now(),
            'opened_at' => null,
            'revoked_at' => null,
            'status' => SurveySupervisorReviewer::STATUS_NOT_OPENED,
        ])->save();

        $this->activityLogger->log('survey_supervisor_review_link.generated', $user, $survey->project, $reviewer, [
            'survey_supervisor_review_round_id' => $reviewer->survey_supervisor_review_round_id,
            'survey_supervisor_reviewer_id' => $reviewer->getKey(),
            'survey_id' => $survey->getKey(),
            'research_project_id' => $survey->project_id,
            'status' => $reviewer->status,
        ], $request);

        return new SupervisorReviewLinkGenerationResult(
            reviewer: $reviewer,
            rawToken: $rawToken,
            url: route('supervisor-review.survey.show', ['token' => $rawToken]),
        );
    }
}
