<?php

namespace App\Policies;

use App\Models\AnalysisJob;
use App\Models\AnalysisResult;
use App\Models\User;

class AnalysisPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function view(User $user, AnalysisResult|AnalysisJob $analysis): bool
    {
        return $analysis->project->owner_id === $user->getKey();
    }
}
