<?php

namespace App\Modules\Supervision\Actions;

use App\Models\ResearchProject;
use App\Models\SupervisionSession;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UpdateSupervisionSessionAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, ResearchProject $project, SupervisionSession $session, array $data, ?Request $request = null): SupervisionSession
    {
        abort_unless($session->research_project_id === $project->getKey(), 404);
        Gate::forUser($user)->authorize('manageSupervision', $project);

        $session->fill($data);

        if ($session->status === SupervisionSession::STATUS_CLOSED && $session->closed_at === null) {
            $session->closed_at = now();
        }

        $session->save();

        $this->activityLogger->log(
            $session->status === SupervisionSession::STATUS_CLOSED ? 'supervision_session.closed' : 'supervision_session.updated',
            $user,
            $project,
            $session,
            [
                'supervision_session_id' => $session->getKey(),
                'research_project_id' => $project->getKey(),
                'status' => $session->status,
            ],
            $request,
        );

        return $session;
    }
}
