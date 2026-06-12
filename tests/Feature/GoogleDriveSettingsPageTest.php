<?php

namespace Tests\Feature;

use App\Models\DriveConnection;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleDriveSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_drive_settings_page_requires_authentication(): void
    {
        $this->get('/admin/settings/google-drive')->assertRedirect('/admin/login');
    }

    public function test_google_drive_settings_page_shows_safe_disconnected_state(): void
    {
        config()->set('google.client_id', null);
        config()->set('google.client_secret', null);
        config()->set('google.redirect_uri', 'http://127.0.0.1:8001/auth/google/drive/callback');
        config()->set('google.drive_scopes', ['https://www.googleapis.com/auth/drive.file']);

        $user = $this->adminUser();

        $this->actingAs($user)
            ->get('/admin/settings/google-drive')
            ->assertOk()
            ->assertSee('MyRiset')
            ->assertSee('data-testid="drive-status-card"', false)
            ->assertSee('data-testid="oauth-readiness-card"', false)
            ->assertSee('data-testid="redirect-scope-card"', false)
            ->assertSee('data-testid="setup-checklist-card"', false)
            ->assertSee('data-testid="drive-actions-card"', false)
            ->assertSee('data-testid="drive-folder-bootstrap-card"', false)
            ->assertSee('Google Drive Settings')
            ->assertSee('Connect MyRiset to your own Google Drive account.')
            ->assertSee('Connection Status')
            ->assertSee('OAuth Readiness')
            ->assertSee('Redirect URI and Scope')
            ->assertSee('Setup Checklist')
            ->assertSee('Drive Folder Bootstrap')
            ->assertSee('Connect Google Drive first')
            ->assertSee('Not connected')
            ->assertSee('Credentials missing')
            ->assertSee('Not configured')
            ->assertSee('Redirect URI configured')
            ->assertSee('Connect Google Drive unavailable')
            ->assertDontSee('/settings/drive/google/redirect')
            ->assertSee('OAuth credentials are not configured yet')
            ->assertSee('http://127.0.0.1:8001/auth/google/drive/callback')
            ->assertSee('https://www.googleapis.com/auth/drive.file')
            ->assertSee('GOOGLE_CLIENT_ID')
            ->assertSee('GOOGLE_CLIENT_SECRET')
            ->assertDontSee('access_token')
            ->assertDontSee('refresh_token');
    }

    public function test_google_drive_settings_page_shows_oauth_readiness_without_secret_value(): void
    {
        config()->set('google.client_id', 'researchhub-client-id-123456.apps.googleusercontent.com');
        config()->set('google.client_secret', 'client-secret-value-that-must-not-render');
        config()->set('google.redirect_uri', 'http://127.0.0.1:8001/auth/google/drive/callback');
        config()->set('google.drive_scopes', ['https://www.googleapis.com/auth/drive.file']);

        $user = $this->adminUser();

        $this->actingAs($user)
            ->get('/admin/settings/google-drive')
            ->assertOk()
            ->assertSee('MyRiset')
            ->assertSee('data-testid="drive-status-card"', false)
            ->assertSee('data-testid="oauth-readiness-card"', false)
            ->assertSee('data-testid="redirect-scope-card"', false)
            ->assertSee('data-testid="setup-checklist-card"', false)
            ->assertSee('data-testid="drive-actions-card"', false)
            ->assertSee('data-testid="drive-folder-bootstrap-card"', false)
            ->assertSee('Ready')
            ->assertSee('Client ID configured')
            ->assertSee('Client secret configured')
            ->assertSee('Redirect URI configured')
            ->assertSee('Value hidden for security')
            ->assertSee('research...tent.com')
            ->assertSee('/settings/drive/google/redirect')
            ->assertSee('http://127.0.0.1:8001/auth/google/drive/callback')
            ->assertSee('https://www.googleapis.com/auth/drive.file')
            ->assertDontSee('client-secret-value-that-must-not-render')
            ->assertDontSee('access_token')
            ->assertDontSee('refresh_token');
    }

    public function test_google_drive_settings_page_shows_connected_metadata_without_tokens(): void
    {
        $user = $this->adminUser();

        DriveConnection::create([
            'user_id' => $user->id,
            'provider' => DriveConnection::PROVIDER_GOOGLE,
            'email' => 'researcher@example.test',
            'access_token' => 'plain-access-value-page',
            'refresh_token' => 'plain-refresh-value-page',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/drive.file'],
            'status' => DriveConnection::STATUS_CONNECTED,
            'last_connected_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/admin/settings/google-drive')
            ->assertOk()
            ->assertSee('Connected')
            ->assertSee('Credentials missing')
            ->assertSee('researcher@example.test')
            ->assertSee('Disconnect / Revoke Connection')
            ->assertSee('Create MyRiset Folders')
            ->assertSee('/settings/drive/google/disconnect')
            ->assertSee('/settings/drive/google/bootstrap-folders')
            ->assertSee('Disconnect Google Drive for this user?', false)
            ->assertDontSee('plain-access-value-page')
            ->assertDontSee('plain-refresh-value-page')
            ->assertDontSee('access_token')
            ->assertDontSee('refresh_token');
    }

    public function test_google_drive_settings_page_only_shows_current_users_connection(): void
    {
        $user = $this->adminUser();
        $otherUser = $this->adminUser();

        DriveConnection::create([
            'user_id' => $otherUser->id,
            'provider' => DriveConnection::PROVIDER_GOOGLE,
            'email' => 'other-user@example.test',
            'access_token' => 'other-access-token-value',
            'refresh_token' => 'other-refresh-token-value',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/drive.file'],
            'status' => DriveConnection::STATUS_CONNECTED,
            'last_connected_at' => now(),
        ]);

        DriveConnection::create([
            'user_id' => $user->id,
            'provider' => DriveConnection::PROVIDER_GOOGLE,
            'email' => 'current-user@example.test',
            'access_token' => 'current-access-token-value',
            'refresh_token' => 'current-refresh-token-value',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/drive.file'],
            'status' => DriveConnection::STATUS_CONNECTED,
            'last_connected_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/admin/settings/google-drive')
            ->assertOk()
            ->assertSee('current-user@example.test')
            ->assertDontSee('other-user@example.test')
            ->assertDontSee('current-access-token-value')
            ->assertDontSee('current-refresh-token-value')
            ->assertDontSee('other-access-token-value')
            ->assertDontSee('other-refresh-token-value');
    }

    private function adminUser(): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
