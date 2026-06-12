<?php

namespace App\Modules\Validation\Actions;

use App\Models\ExpertValidator;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeleteExpertValidatorAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, ExpertValidator $expertValidator, ?Request $request = null): void
    {
        Gate::forUser($user)->authorize('delete', $expertValidator);

        $this->activityLogger->log('expert_validator.deleted', $user, null, $expertValidator, [
            'expert_validator_id' => $expertValidator->getKey(),
            'is_active' => $expertValidator->is_active,
            'is_global' => $expertValidator->is_global,
            'assignment_count' => $expertValidator->assignments()->count(),
        ], $request);

        $expertValidator->delete();
    }
}
