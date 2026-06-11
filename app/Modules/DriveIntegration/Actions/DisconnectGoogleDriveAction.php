<?php

namespace App\Modules\DriveIntegration\Actions;

use App\Models\DriveConnection;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;

class DisconnectGoogleDriveAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, ?Request $request = null): ?DriveConnection
    {
        $connection = $user->googleDriveConnection()->first();

        if ($connection === null) {
            return null;
        }

        $connection->markDisconnected();

        $this->activityLogger->log(
            'drive.disconnected',
            $user,
            null,
            $connection,
            ['provider' => DriveConnection::PROVIDER_GOOGLE],
            $request,
        );

        return $connection;
    }
}
