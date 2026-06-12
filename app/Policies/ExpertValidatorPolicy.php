<?php

namespace App\Policies;

use App\Models\ExpertValidator;
use App\Models\User;

class ExpertValidatorPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('expert_validators.view_any');
    }

    public function view(User $user, ExpertValidator $expertValidator): bool
    {
        if (! $user->can('expert_validators.view')) {
            return false;
        }

        return $expertValidator->created_by === $user->getKey()
            || ($expertValidator->is_global && $expertValidator->is_active);
    }

    public function create(User $user): bool
    {
        return $user->can('expert_validators.create');
    }

    public function update(User $user, ExpertValidator $expertValidator): bool
    {
        return $user->can('expert_validators.update')
            && $expertValidator->created_by === $user->getKey();
    }

    public function delete(User $user, ExpertValidator $expertValidator): bool
    {
        return $user->can('expert_validators.delete')
            && $expertValidator->created_by === $user->getKey();
    }
}
