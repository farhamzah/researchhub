<?php

namespace App\Filament\Pages;

use App\Models\DriveConnection;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UnitEnum;

class GoogleDriveSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cloud';

    protected static string|UnitEnum|null $navigationGroup = 'Integrations';

    protected static ?string $navigationLabel = 'Google Drive';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'settings/google-drive';

    protected static ?string $title = 'Google Drive Settings';

    protected string $view = 'filament.pages.google-drive-settings';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $connection = Auth::user()?->googleDriveConnection()->first();
        $clientId = (string) config('google.client_id');
        $clientSecret = (string) config('google.client_secret');
        $configuredRedirectUri = (string) config('google.redirect_uri');
        $routeRedirectUri = route('drive.google.callback');
        $requiredScopes = config('google.drive_scopes', []);
        $clientIdConfigured = filled($clientId);
        $clientSecretConfigured = filled($clientSecret);
        $redirectUriConfigured = filled($configuredRedirectUri);
        $credentialsConfigured = $clientIdConfigured && $clientSecretConfigured && $redirectUriConfigured;
        $isConnected = $connection?->status === DriveConnection::STATUS_CONNECTED;
        $tokenExpired = $connection?->token_expires_at?->isPast() ?? false;
        $redirectUriMismatch = $redirectUriConfigured && $configuredRedirectUri !== $routeRedirectUri;

        return [
            'connection' => $connection,
            'isConnected' => $isConnected,
            'tokenExpired' => $tokenExpired,
            'healthStatus' => $this->healthStatus($connection, $credentialsConfigured, $tokenExpired),
            'clientIdConfigured' => $clientIdConfigured,
            'clientSecretConfigured' => $clientSecretConfigured,
            'redirectUriConfigured' => $redirectUriConfigured,
            'credentialsConfigured' => $credentialsConfigured,
            'maskedClientId' => $this->maskedClientId($clientId),
            'configuredRedirectUri' => $configuredRedirectUri,
            'routeRedirectUri' => $routeRedirectUri,
            'redirectUriMismatch' => $redirectUriMismatch,
            'requiredScopes' => $requiredScopes,
            'primaryScope' => $requiredScopes[0] ?? 'https://www.googleapis.com/auth/drive.file',
            'connectUrl' => route('drive.google.redirect'),
            'disconnectUrl' => route('drive.google.disconnect'),
            'refreshUrl' => route('filament.admin.pages.settings.google-drive'),
            'statusUrl' => route('drive.google.status'),
            'productionRedirectUri' => 'https://myriset.net/auth/google/drive/callback',
        ];
    }

    private function healthStatus(?DriveConnection $connection, bool $credentialsConfigured, bool $tokenExpired): string
    {
        if (! $credentialsConfigured) {
            return 'Credentials missing';
        }

        if ($connection?->status === DriveConnection::STATUS_FAILED) {
            return 'Connection failed';
        }

        if ($connection?->status === DriveConnection::STATUS_CONNECTED && $tokenExpired) {
            return 'Token expired';
        }

        if ($connection?->status === DriveConnection::STATUS_CONNECTED) {
            return 'Healthy';
        }

        return 'Ready to connect';
    }

    private function maskedClientId(string $clientId): string
    {
        if ($clientId === '') {
            return 'Not configured';
        }

        if (strlen($clientId) <= 12) {
            return Str::mask($clientId, '*', 4);
        }

        return Str::of($clientId)
            ->substr(0, 8)
            ->append('...')
            ->append(Str::of($clientId)->substr(-8))
            ->toString();
    }
}
