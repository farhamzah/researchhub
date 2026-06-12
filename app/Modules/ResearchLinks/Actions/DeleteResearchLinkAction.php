<?php

namespace App\Modules\ResearchLinks\Actions;

use App\Models\ResearchLink;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\ResearchLinks\Services\ResearchLinkUrlSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeleteResearchLinkAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ResearchLinkUrlSafetyService $urlSafety,
    ) {}

    public function handle(User $user, ResearchLink $researchLink, ?Request $request = null): void
    {
        Gate::forUser($user)->authorize('delete', $researchLink);

        $project = $researchLink->project;

        $this->activityLogger->log('research_link.deleted', $user, $project, $researchLink, [
            'research_link_id' => $researchLink->getKey(),
            'research_project_id' => $researchLink->research_project_id,
            'category' => $researchLink->category,
            'is_pinned' => $researchLink->is_pinned,
            'is_active' => $researchLink->is_active,
            'host' => $this->urlSafety->host($researchLink->url),
        ], $request);

        $researchLink->delete();
    }
}
