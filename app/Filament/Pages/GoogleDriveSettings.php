<?php

namespace App\Filament\Pages;

use App\Models\DriveConnection;
use App\Models\DriveFolder;
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
        $rootFolder = $this->globalFolder(DriveFolder::TYPE_RESEARCHHUB_ROOT);
        $globalFolderCount = $this->globalFolderCount();
        $expectedGlobalFolderCount = 1 + count(config('researchhub_drive.global_folders', []));
        $projectFolderCount = DriveFolder::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('project_id')
            ->where('folder_type', DriveFolder::TYPE_PROJECT_ROOT)
            ->count();
        $lastBootstrapFolder = DriveFolder::query()
            ->where('user_id', Auth::id())
            ->latest('updated_at')
            ->first();

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
            'bootstrapFoldersUrl' => route('drive.google.bootstrap-folders'),
            'refreshUrl' => route('filament.admin.pages.settings.google-drive'),
            'statusUrl' => route('drive.google.status'),
            'productionRedirectUri' => 'https://myriset.net/auth/google/drive/callback',
            'folderBootstrapStatus' => $this->folderBootstrapStatus($globalFolderCount, $expectedGlobalFolderCount),
            'rootFolderName' => (string) config('researchhub_drive.root_folder_name', 'MyRiset'),
            'rootFolderIdPreview' => $this->maskedFolderId((string) $rootFolder?->drive_folder_id),
            'globalFolderCount' => $globalFolderCount,
            'expectedGlobalFolderCount' => $expectedGlobalFolderCount,
            'projectFolderCount' => $projectFolderCount,
            'lastBootstrapAt' => $lastBootstrapFolder?->updated_at?->format('Y-m-d H:i'),
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

    private function globalFolder(string $folderType): ?DriveFolder
    {
        return DriveFolder::query()
            ->where('user_id', Auth::id())
            ->whereNull('project_id')
            ->where('folder_type', $folderType)
            ->first();
    }

    private function globalFolderCount(): int
    {
        return DriveFolder::query()
            ->where('user_id', Auth::id())
            ->whereNull('project_id')
            ->whereIn('folder_type', [
                DriveFolder::TYPE_RESEARCHHUB_ROOT,
                ...collect(config('researchhub_drive.global_folders', []))
                    ->pluck('type')
                    ->map(fn (mixed $type): string => (string) $type)
                    ->all(),
            ])
            ->count();
    }

    private function folderBootstrapStatus(int $globalFolderCount, int $expectedGlobalFolderCount): string
    {
        if ($globalFolderCount <= 0) {
            return 'Not created';
        }

        if ($globalFolderCount < $expectedGlobalFolderCount) {
            return 'Partially created';
        }

        return 'Ready';
    }

    private function maskedFolderId(string $folderId): string
    {
        if ($folderId === '') {
            return 'Not created';
        }

        if (strlen($folderId) <= 12) {
            return Str::mask($folderId, '*', 4);
        }

        return Str::of($folderId)
            ->substr(0, 6)
            ->append('...')
            ->append(Str::of($folderId)->substr(-6))
            ->toString();
    }
}
