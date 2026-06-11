<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\ProjectMember;
use App\Models\ResearchProject;
use App\Models\User;

class DocumentPolicy
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

    public function view(User $user, Document $document): bool
    {
        return $this->canViewProject($user, $document->project);
    }

    public function create(User $user, ResearchProject $project): bool
    {
        return $this->canManageProjectDocuments($user, $project);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->canManageProjectDocuments($user, $document->project);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->canManageProjectDocuments($user, $document->project);
    }

    public function addVersion(User $user, Document $document): bool
    {
        return $this->canManageProjectDocuments($user, $document->project);
    }

    public function updateStatus(User $user, Document $document): bool
    {
        return $this->canManageProjectDocuments($user, $document->project);
    }

    public function createReviewLink(User $user, Document $document): bool
    {
        $project = $document->project;

        return $project->owner_id === $user->getKey()
            || $project->hasActiveMemberWithRole($user, [
                ProjectMember::ROLE_SUPERVISOR,
                ProjectMember::ROLE_CO_SUPERVISOR,
            ]);
    }

    private function canViewProject(User $user, ResearchProject $project): bool
    {
        return $project->owner_id === $user->getKey()
            || $project->hasActiveMember($user);
    }

    private function canManageProjectDocuments(User $user, ResearchProject $project): bool
    {
        return $project->owner_id === $user->getKey()
            || $project->hasActiveMemberWithRole($user, config('project_roles.project_update_roles', []));
    }
}
