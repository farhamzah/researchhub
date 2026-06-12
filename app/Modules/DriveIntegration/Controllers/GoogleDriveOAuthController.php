<?php

namespace App\Modules\DriveIntegration\Controllers;

use App\Models\DriveConnection;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\DriveIntegration\Actions\ConnectGoogleDriveAction;
use App\Modules\DriveIntegration\Actions\DisconnectGoogleDriveAction;
use App\Modules\DriveIntegration\Services\GoogleDriveOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class GoogleDriveOAuthController extends Controller
{
    public function status(Request $request)
    {
        $connection = $request->user()->googleDriveConnection()->first();

        return response()->json([
            'provider' => DriveConnection::PROVIDER_GOOGLE,
            'connected' => $connection?->status === DriveConnection::STATUS_CONNECTED,
            'status' => $connection?->status,
            'email' => $connection?->email,
            'scopes' => $connection?->scopes ?? [],
            'token_expires_at' => $connection?->token_expires_at?->toISOString(),
            'last_connected_at' => $connection?->last_connected_at?->toISOString(),
            'last_error' => $connection?->last_error,
        ]);
    }

    public function redirect(Request $request, GoogleDriveOAuthService $oauth): RedirectResponse
    {
        if (! filled(config('google.client_id'))
            || ! filled(config('google.client_secret'))
            || ! filled(config('google.redirect_uri'))) {
            return redirect()->route('filament.admin.pages.settings.google-drive')->withErrors([
                'google_drive' => 'Google Drive OAuth credentials are not configured yet.',
            ]);
        }

        $state = Str::random(40);

        $request->session()->put('google_drive_oauth_state', $state);

        return redirect()->away($oauth->authorizationUrl($state));
    }

    public function callback(
        Request $request,
        GoogleDriveOAuthService $oauth,
        ConnectGoogleDriveAction $connect,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $expectedState = $request->session()->pull('google_drive_oauth_state');
        $receivedState = $request->query('state');

        if (! is_string($expectedState)
            || ! is_string($receivedState)
            || $expectedState === ''
            || ! hash_equals($expectedState, $receivedState)) {
            abort(403);
        }

        try {
            $token = $oauth->fetchToken((string) $request->query('code'));
            $profile = $oauth->userInfo($token);
            $scopes = $this->scopesFromToken($token);

            $connect->handle($request->user(), $token, $profile, $scopes, $request);

            return redirect()->route('drive.google.status')->with('status', 'google-drive-connected');
        } catch (\Throwable $exception) {
            $activityLogger->log(
                'drive.connection_failed',
                $request->user(),
                null,
                null,
                [
                    'provider' => DriveConnection::PROVIDER_GOOGLE,
                    'reason' => $this->safeFailureReason($exception),
                ],
                $request,
            );

            return redirect()->route('drive.google.status')->withErrors([
                'google_drive' => 'Google Drive connection failed.',
            ]);
        }
    }

    public function disconnect(Request $request, DisconnectGoogleDriveAction $disconnect): RedirectResponse
    {
        $disconnect->handle($request->user(), $request);

        return redirect()->route('drive.google.status')->with('status', 'google-drive-disconnected');
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<int, string>
     */
    private function scopesFromToken(array $token): array
    {
        $scope = $token['scope'] ?? implode(' ', config('google.drive_scopes', []));

        if (is_array($scope)) {
            return array_values(array_filter(array_map('strval', $scope)));
        }

        return array_values(array_filter(explode(' ', (string) $scope)));
    }

    private function safeFailureReason(\Throwable $exception): string
    {
        return class_basename($exception);
    }
}
