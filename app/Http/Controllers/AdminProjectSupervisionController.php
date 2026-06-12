<?php

namespace App\Http\Controllers;

use App\Models\ExpertValidator;
use App\Models\ResearchProject;
use App\Models\SupervisionReviewLink;
use App\Models\SupervisionSession;
use App\Modules\Supervision\Actions\CreateSupervisionSessionAction;
use App\Modules\Supervision\Actions\GenerateSupervisionReviewLinkAction;
use App\Modules\Supervision\Actions\RevokeSupervisionReviewLinkAction;
use App\Modules\Supervision\Actions\UpdateSupervisionSessionAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminProjectSupervisionController extends Controller
{
    public function index(ResearchProject $researchProject, Request $request): View
    {
        Gate::authorize('viewSupervision', $researchProject);

        $researchProject->load([
            'owner',
            'expertValidatorAssignments.validator',
            'supervisionSessions.creator',
            'supervisionSessions.reviewLinks.validator',
            'supervisionSessions.reviewLinks.feedback',
            'supervisionSessions.feedback.reviewLink.validator',
        ]);

        return view('projects.admin.supervision.index', [
            'project' => $researchProject,
            'sessions' => $researchProject->supervisionSessions,
            'availableValidators' => $this->availableValidators($researchProject, $request),
            'meetingTypeLabels' => SupervisionSession::MEETING_TYPE_LABELS,
            'sessionStatusLabels' => SupervisionSession::STATUS_LABELS,
            'linkStatusLabels' => SupervisionReviewLink::STATUS_LABELS,
            'canManageSupervision' => $request->user()?->can('manageSupervision', $researchProject) ?? false,
        ]);
    }

    public function storeSession(
        ResearchProject $researchProject,
        Request $request,
        CreateSupervisionSessionAction $createSession,
    ): RedirectResponse {
        $createSession->handle($request->user(), $researchProject, $this->sessionData($request), $request);

        return redirect()
            ->route('admin.projects.supervision.index', ['researchProject' => $researchProject])
            ->with('status', 'supervision-session-created');
    }

    public function updateSession(
        ResearchProject $researchProject,
        SupervisionSession $session,
        Request $request,
        UpdateSupervisionSessionAction $updateSession,
    ): RedirectResponse {
        $updateSession->handle($request->user(), $researchProject, $session, $this->sessionData($request), $request);

        return redirect()
            ->route('admin.projects.supervision.index', ['researchProject' => $researchProject])
            ->with('status', 'supervision-session-updated');
    }

    public function generateLink(
        ResearchProject $researchProject,
        SupervisionSession $session,
        Request $request,
        GenerateSupervisionReviewLinkAction $generateLink,
    ): RedirectResponse {
        $result = $generateLink->handle($request->user(), $researchProject, $session, $this->linkData($request), $request);

        return redirect()
            ->route('admin.projects.supervision.index', ['researchProject' => $researchProject])
            ->with('generated_supervision_url', $result->url)
            ->with('generated_supervision_link_id', $result->reviewLink->getKey())
            ->with('status', 'supervision-link-generated');
    }

    public function revokeLink(
        ResearchProject $researchProject,
        SupervisionReviewLink $reviewLink,
        Request $request,
        RevokeSupervisionReviewLinkAction $revokeLink,
    ): RedirectResponse {
        $revokeLink->handle($request->user(), $researchProject, $reviewLink, $request);

        return redirect()
            ->route('admin.projects.supervision.index', ['researchProject' => $researchProject])
            ->with('status', 'supervision-link-revoked');
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'meeting_type' => ['required', 'string', Rule::in(SupervisionSession::MEETING_TYPES)],
            'status' => ['required', 'string', Rule::in(SupervisionSession::STATUSES)],
            'target_date' => ['nullable', 'date'],
            'agenda' => ['nullable', 'string', 'max:10000'],
            'progress_report' => ['nullable', 'string', 'max:10000'],
            'questions' => ['nullable', 'string', 'max:10000'],
            'requested_feedback' => ['nullable', 'string', 'max:10000'],
            'next_plan' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function linkData(Request $request): array
    {
        return $request->validate([
            'expert_validator_id' => ['nullable', 'string', Rule::exists('expert_validators', 'id')],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_role' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function availableValidators(ResearchProject $project, Request $request): array
    {
        $projectAssignedIds = $project->expertValidatorAssignments()->pluck('expert_validator_id')->all();

        $query = ExpertValidator::query()
            ->visibleTo($request->user())
            ->where('is_active', true);

        if ($projectAssignedIds !== []) {
            $query->orderByRaw('case when id in ('.implode(',', array_fill(0, count($projectAssignedIds), '?')).') then 0 else 1 end', $projectAssignedIds);
        }

        return $query
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ExpertValidator $validator): array => [
                $validator->getKey() => $this->validatorLabel($validator, in_array($validator->getKey(), $projectAssignedIds, true)),
            ])
            ->all();
    }

    private function validatorLabel(ExpertValidator $validator, bool $assignedToProject): string
    {
        return collect([
            $validator->name,
            $validator->institution,
            $assignedToProject ? 'project assigned' : ($validator->is_global ? 'global' : 'private'),
        ])
            ->filter()
            ->join(' - ');
    }
}
