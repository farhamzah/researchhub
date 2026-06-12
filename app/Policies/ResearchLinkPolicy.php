<?php

namespace App\Policies;

use App\Models\ProjectMember;
use App\Models\ResearchLink;
use App\Models\User;

class ResearchLinkPolicy
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

    public function view(User $user, ResearchLink $researchLink): bool
    {
        if ($researchLink->research_project_id === null) {
            return $researchLink->created_by === $user->getKey();
        }

        return $researchLink->project?->owner_id === $user->getKey()
            || ($researchLink->project?->hasActiveMember($user) ?? false);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ResearchLink $researchLink): bool
    {
        if ($researchLink->research_project_id === null) {
            return $researchLink->created_by === $user->getKey();
        }

        return $researchLink->project?->owner_id === $user->getKey()
            || ($researchLink->project?->hasActiveMemberWithRole($user, [
                ProjectMember::ROLE_CO_RESEARCHER,
            ]) ?? false);
    }

    public function delete(User $user, ResearchLink $researchLink): bool
    {
        return $this->update($user, $researchLink);
    }
}
