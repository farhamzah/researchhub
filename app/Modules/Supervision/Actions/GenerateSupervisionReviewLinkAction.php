<?php

namespace App\Modules\Supervision\Actions;

use App\Models\ExpertValidator;
use App\Models\ResearchProject;
use App\Models\SupervisionReviewLink;
use App\Models\SupervisionSession;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Supervision\DTOs\SupervisionLinkGenerationResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GenerateSupervisionReviewLinkAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, ResearchProject $project, SupervisionSession $session, array $data, ?Request $request = null): SupervisionLinkGenerationResult
    {
        abort_unless($session->research_project_id === $project->getKey(), 404);
        Gate::forUser($user)->authorize('manageSupervision', $project);

        if (filled($data['expert_validator_id'] ?? null)) {
            $this->ensureValidatorIsSelectable($user, $project, (string) $data['expert_validator_id']);
        } elseif (blank($data['recipient_name'] ?? null)) {
            throw ValidationException::withMessages([
                'recipient_name' => 'Recipient name is required when no supervisor registry record is selected.',
            ]);
        }

        $rawToken = Str::random(64);

        $reviewLink = SupervisionReviewLink::create([
            'supervision_session_id' => $session->getKey(),
            'expert_validator_id' => $data['expert_validator_id'] ?? null,
            'created_by' => $user->getKey(),
            'recipient_name' => $data['recipient_name'] ?? null,
            'recipient_role' => $data['recipient_role'] ?? null,
            'status' => SupervisionReviewLink::STATUS_LINK_GENERATED,
            'token_hash' => SupervisionReviewLink::hashToken($rawToken),
            'token_created_at' => now(),
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        if ($session->status === SupervisionSession::STATUS_DRAFT) {
            $session->forceFill(['status' => SupervisionSession::STATUS_SHARED])->save();
        }

        $this->activityLogger->log('supervision_link.generated', $user, $project, $reviewLink, [
            'supervision_session_id' => $session->getKey(),
            'supervision_review_link_id' => $reviewLink->getKey(),
            'research_project_id' => $project->getKey(),
            'expert_validator_id' => $reviewLink->expert_validator_id,
            'status' => $reviewLink->status,
        ], $request);

        return new SupervisionLinkGenerationResult(
            reviewLink: $reviewLink,
            rawToken: $rawToken,
            url: route('supervision.review.show', ['token' => $rawToken]),
        );
    }

    private function ensureValidatorIsSelectable(User $user, ResearchProject $project, string $validatorId): void
    {
        $isAssignedToProject = $project->expertValidatorAssignments()
            ->where('expert_validator_id', $validatorId)
            ->exists();

        $isVisible = ExpertValidator::query()
            ->visibleTo($user)
            ->whereKey($validatorId)
            ->where('is_active', true)
            ->exists();

        if (! $isAssignedToProject && ! $isVisible) {
            throw ValidationException::withMessages([
                'expert_validator_id' => 'Selected supervisor is not available for this project.',
            ]);
        }
    }
}
