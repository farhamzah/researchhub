<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\DriveIntegration\Controllers\GoogleDriveOAuthController;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GoogleDriveOAuthCallbackAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_and_optional_alias_callback_routes_exist_and_use_same_handler(): void
    {
        $canonical = Route::getRoutes()->getByName('drive.google.callback');
        $alias = Route::getRoutes()->getByName('drive.google.callback.alias');

        $this->assertNotNull($canonical);
        $this->assertNotNull($alias);
        $this->assertSame('auth/google/drive/callback', $canonical->uri());
        $this->assertSame('google/drive/callback', $alias->uri());
        $this->assertContains('auth', $canonical->gatherMiddleware());
        $this->assertContains('auth', $alias->gatherMiddleware());
        $this->assertSame(GoogleDriveOAuthController::class.'@callback', $canonical->getActionName());
        $this->assertSame(GoogleDriveOAuthController::class.'@callback', $alias->getActionName());
    }

    public function test_google_config_supports_canonical_drive_redirect_aliases(): void
    {
        config()->set('google.redirect_uri', 'https://myriset.net/auth/google/drive/callback');

        $this->assertSame('https://myriset.net/auth/google/drive/callback', config('google.redirect_uri'));
        $this->assertStringEndsWith('/auth/google/drive/callback', config('google.redirect_uri'));
    }

    public function test_env_examples_and_docs_use_canonical_callback_as_primary(): void
    {
        $envExample = $this->readProjectFile('.env.example');
        $productionEnvExample = $this->readProjectFile('.env.production.example');
        $blueprint = $this->readProjectFile('docs/MYRISET_GOOGLE_DRIVE_MANAGEMENT_BLUEPRINT.md');
        $envGuide = $this->readProjectFile('docs/MYRISET_PRODUCTION_ENV_GUIDE.md');
        $deploymentChecklist = $this->readProjectFile('docs/MYRISET_PRODUCTION_DEPLOYMENT_CHECKLIST.md');
        $qaChecklist = $this->readProjectFile('docs/MYRISET_E2E_QA_CHECKLIST.md');

        foreach ([$envExample, $productionEnvExample] as $envFile) {
            $this->assertStringContainsString('/auth/google/drive/callback', $envFile);
            $this->assertStringNotContainsString('GOOGLE_DRIVE_REDIRECT_URI=https://myriset.net/google/drive/callback', $envFile);
        }

        foreach ([$blueprint, $envGuide, $deploymentChecklist, $qaChecklist] as $document) {
            $this->assertStringContainsString('http://127.0.0.1:8001/auth/google/drive/callback', $document);
            $this->assertStringContainsString('https://myriset.net/auth/google/drive/callback', $document);
            $this->assertStringContainsString('redirect_uri_mismatch', $document);
            $this->assertStringContainsString('Optional', $document);
            $this->assertStringContainsString('/google/drive/callback', $document);
            $this->assertStringNotContainsString('Do not use the `/google/drive/callback` value as the active redirect URI', $document);
            $this->assertStringNotContainsString('GOOGLE_DRIVE_REDIRECT_URI=https://myriset.net/google/drive/callback', $document);
        }
    }

    public function test_google_drive_settings_page_renders_canonical_redirect_guidance_without_secrets(): void
    {
        config()->set('google.client_id', 'safe-client-id.apps.googleusercontent.com');
        config()->set('google.client_secret', 'client-secret-value-that-must-not-render');
        config()->set('google.redirect_uri', 'http://127.0.0.1:8001/auth/google/drive/callback');
        config()->set('google.drive_scopes', ['https://www.googleapis.com/auth/drive.file']);

        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/admin/settings/google-drive')
            ->assertOk()
            ->assertSee('Canonical redirect URI')
            ->assertSee('Local example redirect URI')
            ->assertSee('Production example redirect URI')
            ->assertSee('http://127.0.0.1:8001/auth/google/drive/callback')
            ->assertSee('https://myriset.net/auth/google/drive/callback')
            ->assertSee('Optional compatibility alias')
            ->assertSee('https://myriset.net/google/drive/callback')
            ->assertSee('redirect_uri_mismatch')
            ->assertSee('https://www.googleapis.com/auth/drive.file')
            ->assertDontSee('client-secret-value-that-must-not-render')
            ->assertDontSee('access_token')
            ->assertDontSee('refresh_token')
            ->assertDontSee('google_drive_oauth_state');
    }

    private function readProjectFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents, $path.' should be readable.');

        return $contents;
    }
}
