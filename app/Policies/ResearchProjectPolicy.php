<?php

namespace App\Policies;

use App\Models\ResearchProject;
use App\Models\User;

class ResearchProjectPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ResearchProject $researchProject): bool
    {
        return $this->ownsProject($user, $researchProject)
            || $researchProject->hasActiveMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ResearchProject $researchProject): bool
    {
        return $this->ownsProject($user, $researchProject)
            || $researchProject->hasActiveMemberWithRole($user, config('project_roles.project_update_roles', []));
    }

    public function delete(User $user, ResearchProject $researchProject): bool
    {
        return $this->ownsProject($user, $researchProject);
    }

    public function restore(User $user, ResearchProject $researchProject): bool
    {
        return $this->ownsProject($user, $researchProject);
    }

    public function forceDelete(User $user, ResearchProject $researchProject): bool
    {
        return $this->ownsProject($user, $researchProject);
    }

    public function manageMembers(User $user, ResearchProject $researchProject): bool
    {
        return $this->ownsProject($user, $researchProject)
            || $researchProject->hasActiveMemberWithRole($user, config('project_roles.member_management_roles', []));
    }

    public function bootstrapDriveFolders(User $user, ResearchProject $researchProject): bool
    {
        return $this->ownsProject($user, $researchProject);
    }

    public function viewTimeline(User $user, ResearchProject $researchProject): bool
    {
        return $this->view($user, $researchProject);
    }

    public function manageTimeline(User $user, ResearchProject $researchProject): bool
    {
        return $this->ownsProject($user, $researchProject)
            || $researchProject->hasActiveMemberWithRole($user, config('project_roles.project_update_roles', []));
    }

    private function ownsProject(User $user, ResearchProject $researchProject): bool
    {
        return $researchProject->owner_id === $user->getKey();
    }
}
