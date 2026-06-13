<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MyRisetProductionCheckCommand extends Command
{
    protected $signature = 'myriset:production-check';

    protected $description = 'Run safe MyRiset production readiness checks without printing secrets.';

    /**
     * @var array<int, array{level: string, message: string}>
     */
    private array $results = [];

    public function handle(): int
    {
        $this->results = [];

        $this->checkAppEnvironment();
        $this->checkAppUrl();
        $this->checkDatabaseConfig();
        $this->checkWritablePaths();
        $this->checkStorageLink();
        $this->checkGoogleDriveConfig();
        $this->checkRuntimeDrivers();

        foreach ($this->results as $result) {
            $line = sprintf('[%s] %s', $result['level'], $result['message']);

            match ($result['level']) {
                'PASS' => $this->line("<info>{$line}</info>"),
                'WARN' => $this->line("<comment>{$line}</comment>"),
                'FAIL' => $this->line("<error>{$line}</error>"),
                default => $this->line($line),
            };
        }

        return collect($this->results)->contains(fn (array $result): bool => $result['level'] === 'FAIL')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function checkAppEnvironment(): void
    {
        $environment = (string) config('app.env');

        $this->record(
            $environment === 'production' ? 'PASS' : 'WARN',
            $environment === 'production'
                ? 'APP_ENV=production'
                : 'APP_ENV is not production. Run this again on the production server after setting APP_ENV=production.'
        );

        $this->record(
            config('app.debug') === false ? 'PASS' : 'FAIL',
            config('app.debug') === false
                ? 'APP_DEBUG=false'
                : 'APP_DEBUG must be false in production.'
        );

        $this->record(
            filled(config('app.key')) ? 'PASS' : 'FAIL',
            filled(config('app.key'))
                ? 'APP_KEY is configured.'
                : 'APP_KEY is missing. Generate it on the server with php artisan key:generate.'
        );
    }

    private function checkAppUrl(): void
    {
        $url = (string) config('app.url');
        $isHttps = str_starts_with($url, 'https://');

        $this->record(
            $isHttps ? 'PASS' : 'FAIL',
            $isHttps
                ? 'APP_URL uses HTTPS.'
                : 'APP_URL must use https:// for production, for example https://myriset.net.'
        );
    }

    private function checkDatabaseConfig(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $username = (string) config("database.connections.{$connection}.username");

        $this->record($connection === 'pgsql' ? 'PASS' : 'WARN', "DB_CONNECTION={$connection}.");
        $this->record(filled($database) ? 'PASS' : 'FAIL', filled($database) ? 'Database name is configured.' : 'Database name is missing.');
        $this->record(filled($username) ? 'PASS' : 'FAIL', filled($username) ? 'Database username is configured.' : 'Database username is missing.');
    }

    private function checkWritablePaths(): void
    {
        foreach ([storage_path(), base_path('bootstrap/cache'), storage_path('app')] as $path) {
            $this->record(
                File::isDirectory($path) ? 'PASS' : 'FAIL',
                File::isDirectory($path)
                    ? $this->relativePath($path).' directory exists.'
                    : $this->relativePath($path).' directory is missing.'
            );
        }
    }

    private function checkStorageLink(): void
    {
        $publicStorage = public_path('storage');

        $this->record(
            File::exists($publicStorage) ? 'PASS' : 'WARN',
            File::exists($publicStorage)
                ? 'public/storage exists for public disk assets.'
                : 'public/storage is missing. Run php artisan storage:link if public disk assets are needed.'
        );
    }

    private function checkGoogleDriveConfig(): void
    {
        $clientId = config('google.client_id');
        $clientSecret = config('google.client_secret');
        $redirectUri = config('google.redirect_uri');

        $configured = filled($clientId) && filled($clientSecret) && filled($redirectUri);

        $this->record(
            $configured ? 'PASS' : 'WARN',
            $configured
                ? 'Google Drive OAuth appears configured. Secret values are intentionally hidden.'
                : 'Google Drive OAuth is not configured. Drive features can remain disabled until OAuth is ready.'
        );
    }

    private function checkRuntimeDrivers(): void
    {
        $this->record('PASS', 'QUEUE_CONNECTION='.config('queue.default').'.');
        $this->record('PASS', 'CACHE_STORE='.config('cache.default').'.');
        $this->record('PASS', 'SESSION_DRIVER='.config('session.driver').'.');
        $this->record('PASS', 'FILESYSTEM_DISK='.config('filesystems.default').'.');
    }

    private function record(string $level, string $message): void
    {
        $this->results[] = [
            'level' => $level,
            'message' => $message,
        ];
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
