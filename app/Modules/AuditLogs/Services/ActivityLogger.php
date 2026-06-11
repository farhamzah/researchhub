<?php

namespace App\Modules\AuditLogs\Services;

use App\Models\ActivityLog;
use App\Models\ResearchProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogger
{
    public function log(
        string $action,
        ?User $user = null,
        ?ResearchProject $project = null,
        ?Model $subject = null,
        array $metadata = [],
        ?Request $request = null,
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $user?->getKey(),
            'project_id' => $project?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata ?: null,
        ]);
    }
}
