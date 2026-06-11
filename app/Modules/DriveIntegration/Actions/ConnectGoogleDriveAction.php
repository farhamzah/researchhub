<?php

namespace App\Modules\DriveIntegration\Actions;

use App\Models\DriveConnection;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ConnectGoogleDriveAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $token
     * @param  array{id?: string|null, email?: string|null}  $profile
     * @param  array<int, string>  $scopes
     */
    public function handle(User $user, array $token, array $profile, array $scopes, ?Request $request = null): DriveConnection
    {
        $connection = DriveConnection::updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'provider' => DriveConnection::PROVIDER_GOOGLE,
            ],
            [
                'provider_user_id' => $profile['id'] ?? null,
                'email' => $profile['email'] ?? null,
                'access_token' => $token['access_token'] ?? null,
                'refresh_token' => $token['refresh_token'] ?? null,
                'token_expires_at' => $this->expiresAt($token['expires_in'] ?? null),
                'scopes' => $scopes,
                'status' => DriveConnection::STATUS_CONNECTED,
                'last_connected_at' => now(),
                'last_error' => null,
            ],
        );

        $this->activityLogger->log(
            'drive.connected',
            $user,
            null,
            $connection,
            [
                'provider' => DriveConnection::PROVIDER_GOOGLE,
                'email' => $connection->email,
                'scopes' => $scopes,
            ],
            $request,
        );

        return $connection;
    }

    private function expiresAt(mixed $expiresIn): ?Carbon
    {
        if (! is_numeric($expiresIn)) {
            return null;
        }

        return now()->addSeconds((int) $expiresIn);
    }
}
