<?php

namespace App\Modules\SupervisorReviews\Actions;

use App\Models\ResearchProject;
use App\Models\SurveySupervisorReviewer;
use App\Models\SurveySupervisorReviewerHub;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\SupervisorReviews\DTOs\SupervisorReviewerHubLinkGenerationResult;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSupervisorReviewerHubAction
{
    private const REQUIRED_CODES = [
        'S01-STUDENT-NEEDS',
        'S02-LECTURER-NEEDS',
        'S03-PRACTITIONER-INTERVIEW',
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /** @param Collection<int, SurveySupervisorReviewer> $reviewers */
    public function handle(User $actor, ResearchProject $project, Collection $reviewers, CarbonInterface $expiresAt): SupervisorReviewerHubLinkGenerationResult
    {
        $reviewers->each->loadMissing('round.survey');
        $first = $reviewers->first();

        if (! $first || $reviewers->count() !== 3) {
            throw ValidationException::withMessages(['reviewers' => 'Reviewer Hub harus memuat tepat tiga instrumen.']);
        }

        $reviewers->each(function (SurveySupervisorReviewer $reviewer) use ($actor, $project, $first): void {
            Gate::forUser($actor)->authorize('manageSupervisorReview', $reviewer->round->survey);
            if ($reviewer->round->research_project_id !== $project->getKey()
                || $reviewer->supervisor_code !== $first->supervisor_code
                || $reviewer->supervisor_name !== $first->supervisor_name) {
                throw ValidationException::withMessages(['reviewers' => 'Seluruh assignment harus milik proyek dan pembimbing yang sama.']);
            }
        });

        $codes = $reviewers->pluck('round.survey.instrument_code')->sort()->values()->all();
        $required = collect(self::REQUIRED_CODES)->sort()->values()->all();
        if ($codes !== $required) {
            throw ValidationException::withMessages(['reviewers' => 'Reviewer Hub harus mencakup S01, S02, dan S03 tepat satu kali.']);
        }

        return DB::transaction(function () use ($actor, $project, $reviewers, $first, $expiresAt): SupervisorReviewerHubLinkGenerationResult {
            $hub = SurveySupervisorReviewerHub::create([
                'research_project_id' => $project->getKey(),
                'created_by' => $actor->getKey(),
                'supervisor_name' => $first->supervisor_name,
                'supervisor_email' => $first->supervisor_email,
                'supervisor_code' => $first->supervisor_code,
                'expires_at' => $expiresAt,
            ]);

            $reviewers->each(function (SurveySupervisorReviewer $reviewer) use ($hub): void {
                $reviewer->forceFill([
                    'reviewer_hub_id' => $hub->getKey(),
                    'token_hash' => null,
                    'token_created_at' => null,
                    'revoked_at' => now(),
                ])->save();
            });

            $rawToken = Str::random(64);
            $hub->forceFill([
                'token_hash' => SurveySupervisorReviewerHub::hashToken($rawToken),
                'token_created_at' => now(),
                'opened_at' => null,
                'revoked_at' => null,
                'expires_at' => $expiresAt,
            ])->save();

            $this->activityLogger->log('survey_supervisor_reviewer_hub.generated', $actor, $project, $hub, [
                'research_project_id' => $project->getKey(),
                'survey_supervisor_reviewer_hub_id' => $hub->getKey(),
                'reviewer_count' => $reviewers->count(),
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
