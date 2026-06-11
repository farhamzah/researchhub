<?php

namespace App\Policies;

use App\Models\ProjectMember;
use App\Models\Survey;
use App\Models\User;

class SurveyPolicy
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

    public function view(User $user, Survey $survey): bool
    {
        return $this->ownsSurveyProject($user, $survey)
            || $survey->project->hasActiveMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Survey $survey): bool
    {
        return $this->canManageSurvey($user, $survey);
    }

    public function delete(User $user, Survey $survey): bool
    {
        return $this->ownsSurveyProject($user, $survey);
    }

    public function publish(User $user, Survey $survey): bool
    {
        return $this->canManageSurvey($user, $survey);
    }

    public function close(User $user, Survey $survey): bool
    {
        return $this->canManageSurvey($user, $survey);
    }

    public function exportResponses(User $user, Survey $survey): bool
    {
        return $this->canManageSurvey($user, $survey);
    }

    private function canManageSurvey(User $user, Survey $survey): bool
    {
        return $this->ownsSurveyProject($user, $survey)
            || $survey->project->hasActiveMemberWithRole($user, [
                ProjectMember::ROLE_CO_RESEARCHER,
                ProjectMember::ROLE_SUPERVISOR,
                ProjectMember::ROLE_CO_SUPERVISOR,
            ]);
    }

    private function ownsSurveyProject(User $user, Survey $survey): bool
    {
        return $survey->project->owner_id === $user->getKey();
    }
}
