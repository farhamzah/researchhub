<?php

namespace App\Modules\Supervision\Actions;

use App\Models\ResearchProject;
use App\Models\SupervisionSession;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CreateSupervisionSessionAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, ResearchProject $project, array $data, ?Request $request = null): SupervisionSession
    {
        Gate::forUser($user)->authorize('manageSupervision', $project);

        $session = SupervisionSession::create([
            ...$data,
            'research_project_id' => $project->getKey(),
            'created_by' => $user->getKey(),
        ]);

        $this->activityLogger->log('supervision_session.created', $user, $project, $session, [
            'supervision_session_id' => $session->getKey(),
            'research_project_id' => $project->getKey(),
            'status' => $session->status,
        ], $request);

        return $session;
    }
}
