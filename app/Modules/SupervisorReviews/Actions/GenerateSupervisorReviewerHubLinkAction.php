<?php

namespace App\Modules\SupervisorReviews\Actions;

use App\Models\SurveySupervisorReviewerHub;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\SupervisorReviews\DTOs\SupervisorReviewerHubLinkGenerationResult;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GenerateSupervisorReviewerHubLinkAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $actor, SurveySupervisorReviewerHub $hub, CarbonInterface $expiresAt): SupervisorReviewerHubLinkGenerationResult
    {
        $hub->loadMissing('project', 'reviewers.round.survey');

        if ($hub->reviewers->count() !== 3) {
            throw ValidationException::withMessages(['hub' => 'Reviewer Hub harus memuat tepat tiga instrumen.']);
        }

        $hub->reviewers->each(fn ($reviewer) => Gate::forUser($actor)->authorize('manageSupervisorReview', $reviewer->round->survey));

        return DB::transaction(function () use ($actor, $hub, $expiresAt): SupervisorReviewerHubLinkGenerationResult {
            $rawToken = Str::random(64);
            $hub->forceFill([
                'token_hash' => SurveySupervisorReviewerHub::hashToken($rawToken),
                'token_created_at' => now(),
                'revoked_at' => null,
                'expires_at' => $expiresAt,
            ])->save();

            $this->activityLogger->log('survey_supervisor_reviewer_hub.generated', $actor, $hub->project, $hub, [
                'research_project_id' => $hub->research_project_id,
                'survey_supervisor_reviewer_hub_id' => $hub->getKey(),
                'reviewer_count' => $hub->reviewers->count(),
                'supervisor_code' => $hub->supervisor_code,
            ]);

            return new SupervisorReviewerHubLinkGenerationResult(
                hub: $hub->refresh()->load('reviewers.round.survey'),
                rawToken: $rawToken,
                url: route('supervisor-review.hub.show', ['token' => $rawToken]),
            );
        });
    }
}
