<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DriveConnection;
use App\Models\User;
use App\Modules\DriveIntegration\Services\GoogleDriveOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DriveConnectionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_drive_connection_uses_uuid_primary_key_and_encrypted_credentials(): void
    {
        $user = User::factory()->create();

        $connection = DriveConnection::create([
            'user_id' => $user->id,
            'provider' => DriveConnection::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-user-123',
            'email' => 'researcher@example.test',
            'access_token' => 'plain-access-value-123',
            'refresh_token' => 'plain-refresh-value-123',
            'scopes' => ['https://www.googleapis.com/auth/drive.file'],
            'status' => DriveConnection::STATUS_CONNECTED,
            'last_connected_at' => now(),
        ]);

        $raw = DB::table('drive_connections')->where('id', $connection->id)->first();

        $this->assertTrue(Str::isUuid($connection->id));
        $this->assertSame('plain-access-value-123', $connection->fresh()->access_token);
        $this->assertSame('plain-refresh-value-123', $connection->fresh()->refresh_token);
        $this->assertNotSame('plain-access-value-123', $raw->access_token);
        $this->assertNotSame('plain-refresh-value-123', $raw->refresh_token);
    }

    public function test_drive_routes_require_authenticated_user(): void
    {
        $this->get('/settings/drive/google')->assertRedirect('/admin/login');
        $this->get('/settings/drive/google/redirect')->assertRedirect('/admin/login');
        $this->get('/auth/google/drive/callback')->assertRedirect('/admin/login');
        $this->post('/settings/drive/google/disconnect')->assertRedirect('/admin/login');
    }

    public function test_redirect_stores_oauth_state_and_uses_minimum_drive_scope(): void
    {
        config()->set('google.drive_scopes', ['https://www.googleapis.com/auth/drive.file']);

        $user = User::factory()->create();

        $this->mock(GoogleDriveOAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('authorizationUrl')
                ->once()
                ->with(Mockery::on(fn (string $state): bool => strlen($state) === 40))
                ->andReturn('https://accounts.google.com/o/oauth2/v2/auth?state=placeholder');
        });

        $this->actingAs($user)
            ->get('/settings/drive/google/redirect')
            ->assertRedirect('https://accounts.google.com/o/oauth2/v2/auth?state=placeholder')
            ->assertSessionHas('google_drive_oauth_state');

        $this->assertSame(['https://www.googleapis.com/auth/drive.file'], config('google.drive_scopes'));
    }

    public function test_callback_rejects_invalid_state_without_creating_connection(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['google_drive_oauth_state' => 'expected-state'])
            ->get('/auth/google/drive/callback?state=wrong-state&code=placeholder-code')
            ->assertForbidden();

        $this->assertDatabaseCount('drive_connections', 0);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'drive.connected']);
    }

    public function test_callback_stores_connection_and_logs_without_credential_values(): void
    {
        $user = User::factory()->create();

        $this->mock(GoogleDriveOAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('fetchToken')
                ->once()
                ->with('placeholder-code')
                ->andReturn([
                    'access_token' => 'plain-access-value-456',
                    'refresh_token' => 'plain-refresh-value-456',
                    'expires_in' => 3600,
                    'scope' => 'https://www.googleapis.com/auth/drive.file',
                ]);

            $mock->shouldReceive('userInfo')
                ->once()
                ->andReturn([
                    'id' => 'google-user-456',
                    'email' => 'researcher@example.test',
                ]);
        });

        $this->actingAs($user)
            ->withSession(['google_drive_oauth_state' => 'expected-state'])
            ->get('/auth/google/drive/callback?state=expected-state&code=placeholder-code')
            ->assertRedirect(route('drive.google.status'));

        $connection = DriveConnection::firstOrFail();
        $raw = DB::table('drive_connections')->where('id', $connection->id)->first();
        $metadata = ActivityLog::where('action', 'drive.connected')->firstOrFail()->metadata;

        $this->assertSame(DriveConnection::STATUS_CONNECTED, $connection->status);
        $this->assertSame('researcher@example.test', $connection->email);
        $this->assertNotSame('plain-access-value-456', $raw->access_token);
        $this->assertStringNotContainsString('plain-access-value-456', json_encode($metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('plain-refresh-value-456', json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    public function test_status_response_never_exposes_credentials(): void
    {
        $user = User::factory()->create();

        DriveConnection::create([
            'user_id' => $user->id,
            'provider' => DriveConnection::PROVIDER_GOOGLE,
            'email' => 'researcher@example.test',
            'access_token' => 'plain-access-value-789',
            'refresh_token' => 'plain-refresh-value-789',
            'scopes' => ['https://www.googleapis.com/auth/drive.file'],
            'status' => DriveConnection::STATUS_CONNECTED,
            'last_connected_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/settings/drive/google')
            ->assertOk()
            ->assertJsonPath('connected', true)
            ->assertJsonMissingPath('access_token')
            ->assertJsonMissingPath('refresh_token')
            ->assertDontSee('plain-access-value-789')
            ->assertDontSee('plain-refresh-value-789');
    }

    public function test_disconnect_clears_credentials_and_records_safe_activity_log(): void
    {
        $user = User::factory()->create();

        DriveConnection::create([
            'user_id' => $user->id,
            'provider' => DriveConnection::PROVIDER_GOOGLE,
            'email' => 'researcher@example.test',
            'access_token' => 'plain-access-value-000',
            'refresh_token' => 'plain-refresh-value-000',
            'scopes' => ['https://www.googleapis.com/auth/drive.file'],
            'status' => DriveConnection::STATUS_CONNECTED,
            'last_connected_at' => now(),
        ]);

        $this->actingAs($user)
            ->post('/settings/drive/google/disconnect')
            ->assertRedirect(route('drive.google.status'));

        $connection = DriveConnection::firstOrFail();
        $metadata = ActivityLog::where('action', 'drive.disconnected')->firstOrFail()->metadata;

        $this->assertSame(DriveConnection::STATUS_DISCONNECTED, $connection->status);
        $this->assertNull($connection->access_token);
        $this->assertNull($connection->refresh_token);
        $this->assertStringNotContainsString('plain-access-value-000', json_encode($metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('plain-refresh-value-000', json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    public function test_env_example_uses_safe_google_drive_placeholders(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('GOOGLE_CLIENT_ID=', $envExample);
        $this->assertStringContainsString('GOOGLE_CLIENT_SECRET=', $envExample);
        $this->assertStringContainsString('GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/drive/callback"', $envExample);
        $this->assertStringContainsString('GOOGLE_DRIVE_SCOPES="https://www.googleapis.com/auth/drive.file"', $envExample);
        $this->assertStringNotContainsString('client_secret_', $envExample);
    }
}
