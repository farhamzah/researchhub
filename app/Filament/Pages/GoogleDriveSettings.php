<?php

namespace App\Filament\Pages;

use App\Models\DriveConnection;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
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

        return [
            'connection' => $connection,
            'isConnected' => $connection?->status === DriveConnection::STATUS_CONNECTED,
            'credentialsConfigured' => filled(config('google.client_id')) && filled(config('google.client_secret')),
            'requiredScopes' => config('google.drive_scopes', []),
            'connectUrl' => route('drive.google.redirect'),
            'disconnectUrl' => route('drive.google.disconnect'),
        ];
    }
}
