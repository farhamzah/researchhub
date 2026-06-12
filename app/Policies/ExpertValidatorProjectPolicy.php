<?php

namespace App\Policies;

use App\Models\ExpertValidatorProject;
use App\Models\User;

class ExpertValidatorProjectPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function view(User $user, ExpertValidatorProject $assignment): bool
    {
        return $user->can('projects.view') && $user->can('view', $assignment->project);
    }

    public function create(User $user): bool
    {
        return $user->can('projects.manage_validators');
    }

    public function update(User $user, ExpertValidatorProject $assignment): bool
    {
        return $user->can('projects.manage_validators') && $user->can('update', $assignment->project);
    }

    public function delete(User $user, ExpertValidatorProject $assignment): bool
    {
        return $user->can('projects.manage_validators') && $user->can('update', $assignment->project);
    }
}
