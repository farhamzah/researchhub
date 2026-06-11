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
        config()->set('google.drive_scopes', ['https://www.googleapis.com/auth/drive.file']);

        $user = $this->adminUser();

        $this->actingAs($user)
            ->get('/admin/settings/google-drive')
            ->assertOk()
            ->assertSee('Not connected')
            ->assertSee('Connect Google Drive')
            ->assertSee('/settings/drive/google/redirect')
            ->assertSee('Google OAuth credentials are not configured')
            ->assertSee('https://www.googleapis.com/auth/drive.file')
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
            ->assertSee('researcher@example.test')
            ->assertSee('Disconnect Google Drive')
            ->assertSee('/settings/drive/google/disconnect')
            ->assertDontSee('plain-access-value-page')
            ->assertDontSee('plain-refresh-value-page')
            ->assertDontSee('access_token')
            ->assertDontSee('refresh_token');
    }

    private function adminUser(): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
