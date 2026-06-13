<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    public function test_production_check_passes_safe_https_configuration_and_warns_for_optional_drive(): void
    {
        $this->configureProductionReadyApp();
        config()->set('google.client_id', null);
        config()->set('google.client_secret', null);
        config()->set('google.redirect_uri', 'https://myriset.net/auth/google/drive/callback');

        $this->artisan('myriset:production-check')
            ->expectsOutputToContain('[PASS] APP_ENV=production')
            ->expectsOutputToContain('[PASS] APP_DEBUG=false')
            ->expectsOutputToContain('[PASS] APP_URL uses HTTPS.')
            ->expectsOutputToContain('[WARN] Google Drive OAuth is not configured. Drive features can remain disabled until OAuth is ready.')
            ->expectsOutputToContain('super_admin')
            ->assertExitCode(0);
    }

    public function test_production_check_reports_debug_and_non_https_without_printing_secrets(): void
    {
        $this->configureProductionReadyApp();
        config()->set('app.debug', true);
        config()->set('app.url', 'http://myriset.net');
        config()->set('database.connections.pgsql.password', 'super-secret-db-password');
        config()->set('google.client_id', 'safe-client-id.apps.googleusercontent.com');
        config()->set('google.client_secret', 'super-secret-google-client-secret');
        config()->set('google.redirect_uri', 'https://myriset.net/auth/google/drive/callback');

        $this->artisan('myriset:production-check')
            ->expectsOutputToContain('[FAIL] APP_DEBUG must be false in production.')
            ->expectsOutputToContain('[FAIL] APP_URL must use https:// for production, for example https://myriset.net.')
            ->expectsOutputToContain('[PASS] Google Drive OAuth appears configured. Secret values are intentionally hidden.')
            ->doesntExpectOutputToContain('super-secret-db-password')
            ->doesntExpectOutputToContain('super-secret-google-client-secret')
            ->assertExitCode(1);
    }

    public function test_production_documents_and_env_template_are_safe_and_complete(): void
    {
        $deploymentChecklist = file_get_contents(base_path('docs/MYRISET_PRODUCTION_DEPLOYMENT_CHECKLIST.md'));
        $envGuide = file_get_contents(base_path('docs/MYRISET_PRODUCTION_ENV_GUIDE.md'));
        $envTemplate = file_get_contents(base_path('.env.production.example'));

        $this->assertIsString($deploymentChecklist);
        $this->assertIsString($envGuide);
        $this->assertIsString($envTemplate);

        $this->assertStringContainsString('https://myriset.net', $deploymentChecklist);
        $this->assertStringContainsString('APP_DEBUG=false', $deploymentChecklist);
        $this->assertStringContainsString('php artisan migrate --force', $deploymentChecklist);
        $this->assertStringContainsString('Do not run `MyRisetDemoSeeder` in production.', $deploymentChecklist);
        $this->assertStringContainsString('php artisan myriset:create-admin --email=admin@myriset.net --name="MyRiset Admin"', $deploymentChecklist);
        $this->assertStringContainsString('https://myriset.net/auth/google/drive/callback', $deploymentChecklist);
        $this->assertStringContainsString('Rollback Checklist', $deploymentChecklist);

        $this->assertStringContainsString('Generate `APP_KEY` on the server', $envGuide);
        $this->assertStringContainsString('Google Drive credentials can remain empty', $envGuide);
        $this->assertStringContainsString('Enter the password interactively', $envGuide);
        $this->assertStringContainsString('php artisan db:seed --class=MyRisetDemoSeeder', $envGuide);

        $this->assertStringContainsString('APP_KEY=base64:GENERATE_ON_SERVER', $envTemplate);
        $this->assertStringContainsString('DB_PASSWORD=CHANGE_ME_ON_SERVER', $envTemplate);
        $this->assertStringContainsString('GOOGLE_CLIENT_SECRET=', $envTemplate);
        $this->assertStringNotContainsString('admin@researchhub.test', $envTemplate);
        $this->assertStringNotContainsString('super-secret', strtolower($envTemplate));
    }

    private function configureProductionReadyApp(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', false);
        config()->set('app.key', 'base64:production-check-placeholder-key');
        config()->set('app.url', 'https://myriset.net');
        config()->set('database.default', 'pgsql');
        config()->set('database.connections.pgsql.database', 'myriset');
        config()->set('database.connections.pgsql.username', 'myriset_user');
        config()->set('queue.default', 'database');
        config()->set('cache.default', 'file');
        config()->set('session.driver', 'file');
        config()->set('filesystems.default', 'local');
    }
}
