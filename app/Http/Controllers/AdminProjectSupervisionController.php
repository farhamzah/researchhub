<?php

namespace App\Http\Controllers;

use App\Models\ExpertValidator;
use App\Models\ResearchProject;
use App\Models\SupervisionFollowUpItem;
use App\Models\SupervisionReviewLink;
use App\Models\SupervisionSession;
use App\Models\SupervisionSessionResource;
use App\Models\SurveyValidationRound;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\ResearchLinks\Services\ResearchLinkUrlSafetyService;
use App\Modules\Supervision\Actions\CreateSupervisionSessionAction;
use App\Modules\Supervision\Actions\GenerateSupervisionReviewLinkAction;
use App\Modules\Supervision\Actions\RevokeSupervisionReviewLinkAction;
use App\Modules\Supervision\Actions\UpdateSupervisionSessionAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            'supervisionSessions.resources',
            'supervisionSessions.followUpItems.assignee',
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
            'resourceTypeLabels' => SupervisionSessionResource::TYPE_LABELS,
            'followUpStatusLabels' => SupervisionFollowUpItem::STATUS_LABELS,
            'followUpPriorityLabels' => SupervisionFollowUpItem::PRIORITY_LABELS,
            'followUpSourceLabels' => SupervisionFollowUpItem::SOURCE_LABELS,
            'resourceOptions' => $this->resourceOptions($researchProject, $request),
            'assignableUsers' => $this->assignableUsers($researchProject),
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

    public function storeResource(
        ResearchProject $researchProject,
        SupervisionSession $session,
        Request $request,
        ResearchLinkUrlSafetyService $urlSafetyService,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $this->authorizeSessionManagement($researchProject, $session);

        $data = $this->resourceData($request, $researchProject, $urlSafetyService);
        $resource = $session->resources()->create([
            ...$data,
            'created_by' => $request->user()?->getKey(),
        ]);

        $activityLogger->log('supervision_resource.created', $request->user(), $researchProject, $session, [
            'supervision_session_id' => $session->getKey(),
            'research_project_id' => $researchProject->getKey(),
            'resource_type' => $resource->resource_type,
        ], $request);

        return redirect()
            ->route('admin.projects.supervision.index', ['researchProject' => $researchProject])
            ->with('status', 'supervision-resource-created');
    }

    public function updateResource(
        ResearchProject $researchProject,
        SupervisionSession $session,
        SupervisionSessionResource $resource,
        Request $request,
        ResearchLinkUrlSafetyService $urlSafetyService,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $this->authorizeSessionManagement($researchProject, $session);
        abort_unless($resource->supervision_session_id === $session->getKey(), 404);

        $resource->update($this->resourceData($request, $researchProject, $urlSafetyService));

        $activityLogger->log('supervision_resource.updated', $request->user(), $researchProject, $session, [
            'supervision_session_id' => $session->getKey(),
            'research_project_id' => $researchProject->getKey(),
            'resource_type' => $resource->resource_type,
        ], $request);

        return redirect()
            ->route('admin.projects.supervision.index', ['researchProject' => $researchProject])
            ->with('status', 'supervision-resource-updated');
    }

    public function deleteResource(
        ResearchProject $researchProject,
        SupervisionSession $session,
        SupervisionSessionResource $resource,
        Request $request,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $this->authorizeSessionManagement($researchProject, $session);
        abort_unless($resource->supervision_session_id === $session->getKey(), 404);

        $resourceType = $resource->resource_type;
        $resource->delete();

        $activityLogger->log('supervision_resource.deleted', $request->user(), $researchProject, $session, [
            'supervision_session_id' => $session->getKey(),
            'research_project_id' => $researchProject->getKey(),
            'resource_type' => $resourceType,
        ], $request);

        return redirect()
            ->route('admin.projects.supervision.index', ['researchProject' => $researchProject])
            ->with('status', 'supervision-resource-deleted');
    }

    public function storeFollowUp(
        ResearchProject $researchProject,
        SupervisionSession $session,
        Request $request,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $this->authorizeSessionManagement($researchProject, $session);

        $item = $session->followUpItems()->create([
            ...$this->followUpData($request, $researchProject),
            'created_by' => $request->user()?->getKey(),
        ]);

        $activityLogger->log('supervision_follow_up.created', $request->user(), $researchProject, $session, [
            'supervision_session_id' => $session->getKey(),
            'research_project_id' => $researchProject->getKey(),
            'follow_up_item_id' => $item->getKey(),
            'status' => $item->status,
            'priority' => $item->priority,
        ], $request);

        return redirect()
            ->route('admin.projects.supervision.index', ['researchProject' => $researchProject])
            ->with('status', 'supervision-follow-up-created');
    }

    public function updateFollowUp(
        ResearchProject $researchProject,
        SupervisionSession $session,
        SupervisionFollowUpItem $followUp,
        Request $request,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $this->authorizeSessionManagement($researchProject, $session);
        abort_unless($followUp->supervision_session_id === $session->getKey(), 404);

        $previousStatus = $followUp->status;
        $followUp->update($this->followUpData($request, $researchProject));

        $activityLogger->log(
            $followUp->status === SupervisionFollowUpItem::STATUS_COMPLETED && $previousStatus !== SupervisionFollowUpItem::STATUS_COMPLETED
                ? 'supervision_follow_up.completed'
                : 'supervision_follow_up.updated',
            $request->user(),
            $researchProject,
            $session,
            [
                'supervision_session_id' => $session->getKey(),
                'research_project_id' => $researchProject->getKey(),
                'follow_up_item_id' => $followUp->getKey(),
                'status' => $followUp->status,
                'priority' => $followUp->priority,
            ],
            $request,
        );

        return redirect()
            ->route('admin.projects.supervision.index', ['researchProject' => $researchProject])
            ->with('status', 'supervision-follow-up-updated');
    }

    public function deleteFollowUp(
        ResearchProject $researchProject,
        SupervisionSession $session,
        SupervisionFollowUpItem $followUp,
        Request $request,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $this->authorizeSessionManagement($researchProject, $session);
        abort_unless($followUp->supervision_session_id === $session->getKey(), 404);

        $followUpId = $followUp->getKey();
        $status = $followUp->status;
        $priority = $followUp->priority;
        $followUp->delete();

        $activityLogger->log('supervision_follow_up.deleted', $request->user(), $researchProject, $session, [
            'supervision_session_id' => $session->getKey(),
            'research_project_id' => $researchProject->getKey(),
            'follow_up_item_id' => $followUpId,
            'status' => $status,
            'priority' => $priority,
        ], $request);

        return redirect()
            ->route('admin.projects.supervision.index', ['researchProject' => $researchProject])
            ->with('status', 'supervision-follow-up-deleted');
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

    private function authorizeSessionManagement(ResearchProject $project, SupervisionSession $session): void
    {
        Gate::authorize('manageSupervision', $project);
        abort_unless($session->research_project_id === $project->getKey(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function resourceData(Request $request, ResearchProject $project, ResearchLinkUrlSafetyService $urlSafetyService): array
    {
        $data = $request->validate([
            'resource_type' => ['required', 'string', Rule::in(SupervisionSessionResource::TYPES)],
            'resource_id' => ['nullable', 'uuid'],
            'title' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2048'],
            'description' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_visible_to_supervisor' => ['nullable', 'boolean'],
        ]);

        $type = (string) $data['resource_type'];
        $data['is_visible_to_supervisor'] = $request->boolean('is_visible_to_supervisor');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if ($type === SupervisionSessionResource::TYPE_MANUAL_URL) {
            $data['url'] = $urlSafetyService->assertSafe($data['url'] ?? null);
        } else {
            $data['url'] = null;
        }

        if ($type === SupervisionSessionResource::TYPE_RESEARCH_LINK && filled($data['resource_id'] ?? null)) {
            $link = $project->researchLinks()->whereKey($data['resource_id'])->firstOrFail();
            $urlSafetyService->assertSafe($link->url);
        }

        if (in_array($type, [
            SupervisionSessionResource::TYPE_DOCUMENT,
            SupervisionSessionResource::TYPE_RESEARCH_LINK,
            SupervisionSessionResource::TYPE_TIMELINE_TASK,
            SupervisionSessionResource::TYPE_SURVEY,
            SupervisionSessionResource::TYPE_VALIDATION_ROUND,
        ], true)) {
            $this->assertResourceBelongsToProject($project, $type, $data['resource_id'] ?? null);
        } else {
            $data['resource_id'] = null;
        }

        return $data;
    }

    private function assertResourceBelongsToProject(ResearchProject $project, string $type, mixed $resourceId): void
    {
        abort_if(blank($resourceId), 422, 'Related record is required for this resource type.');

        match ($type) {
            SupervisionSessionResource::TYPE_DOCUMENT => $project->documents()->whereKey($resourceId)->firstOrFail(),
            SupervisionSessionResource::TYPE_RESEARCH_LINK => $project->researchLinks()->whereKey($resourceId)->firstOrFail(),
            SupervisionSessionResource::TYPE_TIMELINE_TASK => $project->timelineTasks()->whereKey($resourceId)->firstOrFail(),
            SupervisionSessionResource::TYPE_SURVEY => $project->surveys()->whereKey($resourceId)->firstOrFail(),
            SupervisionSessionResource::TYPE_VALIDATION_ROUND => SurveyValidationRound::query()
                ->where('research_project_id', $project->getKey())
                ->whereKey($resourceId)
                ->firstOrFail(),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function followUpData(Request $request, ResearchProject $project): array
    {
        $data = $request->validate([
            'assigned_to' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'source' => ['nullable', 'string', Rule::in(SupervisionFollowUpItem::SOURCES)],
            'status' => ['required', 'string', Rule::in(SupervisionFollowUpItem::STATUSES)],
            'priority' => ['required', 'string', Rule::in(SupervisionFollowUpItem::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
            'completion_note' => ['nullable', 'string', 'max:10000'],
        ]);

        if (filled($data['assigned_to'] ?? null)) {
            $isAssignable = $project->owner_id === $data['assigned_to']
                || $project->activeMembers()->where('user_id', $data['assigned_to'])->exists();

            abort_unless($isAssignable, 422);
        }

        return $data;
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

    /**
     * @return array<string, array<string, string>>
     */
    private function resourceOptions(ResearchProject $project, Request $request): array
    {
        return [
            SupervisionSessionResource::TYPE_DOCUMENT => $project->documents()
                ->orderBy('title')
                ->pluck('title', 'id')
                ->all(),
            SupervisionSessionResource::TYPE_RESEARCH_LINK => $project->researchLinks()
                ->visibleTo($request->user())
                ->orderBy('title')
                ->pluck('title', 'id')
                ->all(),
            SupervisionSessionResource::TYPE_TIMELINE_TASK => $project->timelineTasks()
                ->orderBy('sort_order')
                ->pluck('title', 'id')
                ->all(),
            SupervisionSessionResource::TYPE_SURVEY => $project->surveys()
                ->orderBy('title')
                ->pluck('title', 'id')
                ->all(),
            SupervisionSessionResource::TYPE_VALIDATION_ROUND => SurveyValidationRound::query()
                ->where('research_project_id', $project->getKey())
                ->orderBy('title')
                ->pluck('title', 'id')
                ->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function assignableUsers(ResearchProject $project): array
    {
        return Collection::make([$project->owner])
            ->merge($project->activeMembers()->with('user')->get()->pluck('user'))
            ->filter()
            ->unique('id')
            ->mapWithKeys(fn ($user): array => [$user->id => $user->name])
            ->all();
    }
}
