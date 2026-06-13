<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_admin_command_creates_super_admin_with_hashed_password(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $password = 'Valid!Bootstrap42';

        $this->artisan('myriset:create-admin --email=admin@myriset.net --name="MyRiset Admin"')
            ->expectsOutputToContain('Current environment: testing')
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutputToContain('[PASS] Admin user created.')
            ->expectsOutputToContain('Email: admin@myriset.net')
            ->expectsOutputToContain('Role: super_admin')
            ->doesntExpectOutputToContain($password)
            ->assertExitCode(0);

        $user = User::query()->where('email', 'admin@myriset.net')->firstOrFail();

        $this->assertSame('MyRiset Admin', $user->name);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotSame($password, $user->password);
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_create_admin_rejects_mismatched_and_weak_passwords(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->artisan('myriset:create-admin --email=mismatch@myriset.net --name="Mismatch Admin"')
            ->expectsQuestion('Password', 'Valid!Bootstrap42')
            ->expectsQuestion('Confirm password', 'Different!Bootstrap42')
            ->expectsOutputToContain('[FAIL] Password confirmation does not match.')
            ->doesntExpectOutputToContain('Valid!Bootstrap42')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'mismatch@myriset.net']);

        $this->artisan('myriset:create-admin --email=weak@myriset.net --name="Weak Admin"')
            ->expectsQuestion('Password', 'Password123!')
            ->expectsQuestion('Confirm password', 'Password123!')
            ->expectsOutputToContain('[FAIL] Password must be at least 12 characters')
            ->doesntExpectOutputToContain('Password123!')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'weak@myriset.net']);
    }

    public function test_existing_user_is_not_overwritten_by_default(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $originalPassword = 'Original!Secret42';
        $user = User::factory()->create([
            'email' => 'existing@myriset.net',
            'password' => Hash::make($originalPassword),
        ]);

        $this->artisan('myriset:create-admin --email=existing@myriset.net')
            ->expectsOutputToContain('[WARN] User already exists. No password was changed.')
            ->doesntExpectOutputToContain($originalPassword)
            ->assertExitCode(0);

        $user->refresh();

        $this->assertTrue(Hash::check($originalPassword, $user->password));
        $this->assertFalse($user->hasRole('super_admin'));
    }

    public function test_promote_existing_assigns_super_admin_without_resetting_password(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $originalPassword = 'Original!Secret42';
        $user = User::factory()->create([
            'email' => 'promote@myriset.net',
            'password' => Hash::make($originalPassword),
        ]);

        $this->artisan('myriset:create-admin --email=promote@myriset.net --promote-existing')
            ->expectsOutputToContain('[PASS] Existing user promoted.')
            ->expectsOutputToContain('Role: super_admin')
            ->doesntExpectOutputToContain($originalPassword)
            ->assertExitCode(0);

        $user->refresh();

        $this->assertTrue(Hash::check($originalPassword, $user->password));
        $this->assertTrue($user->hasRole('super_admin'));
    }

    public function test_reset_password_is_explicit_and_hidden(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $originalPassword = 'Original!Secret42';
        $newPassword = 'Fresh!Bootstrap42';
        $user = User::factory()->create([
            'email' => 'reset@myriset.net',
            'password' => Hash::make($originalPassword),
        ]);

        $this->artisan('myriset:create-admin --email=reset@myriset.net --reset-password')
            ->expectsQuestion('Password', $newPassword)
            ->expectsQuestion('Confirm password', $newPassword)
            ->expectsOutputToContain('[PASS] Existing user password reset.')
            ->doesntExpectOutputToContain($newPassword)
            ->assertExitCode(0);

        $user->refresh();

        $this->assertFalse(Hash::check($originalPassword, $user->password));
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse($user->hasRole('super_admin'));
    }

    public function test_production_environment_requires_confirmation(): void
    {
        $this->seed(RolePermissionSeeder::class);
        config()->set('app.env', 'production');

        $this->artisan('myriset:create-admin --email=prod@myriset.net --name="Production Admin"')
            ->expectsConfirmation('You are creating an admin in production. Continue?', 'no')
            ->expectsOutputToContain('[WARN] Admin creation cancelled.')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'prod@myriset.net']);
    }

    public function test_production_check_reports_super_admin_existence_safely(): void
    {
        $this->seed(RolePermissionSeeder::class);
        config()->set('app.env', 'production');
        config()->set('app.debug', false);
        config()->set('app.key', 'base64:production-check-placeholder-key');
        config()->set('app.url', 'https://myriset.net');
        config()->set('database.connections.sqlite.username', 'sqlite_test_user');
        config()->set('queue.default', 'database');
        config()->set('cache.default', 'file');
        config()->set('session.driver', 'file');
        config()->set('filesystems.default', 'local');

        $this->artisan('myriset:production-check')
            ->expectsOutputToContain('[WARN] No super_admin user found. Run php artisan myriset:create-admin on the server.')
            ->assertExitCode(0);

        $admin = User::factory()->create(['email' => 'super-admin-count@myriset.net']);
        $admin->assignRole('super_admin');

        $this->artisan('myriset:production-check')
            ->expectsOutputToContain('[PASS] At least one super_admin user exists. super_admin users: 1.')
            ->doesntExpectOutputToContain('super-admin-count@myriset.net')
            ->assertExitCode(0);
    }

    public function test_no_public_route_or_password_option_is_added_for_admin_creation(): void
    {
        $this->assertFalse(Route::has('myriset.create-admin'));

        $this->artisan('help myriset:create-admin')
            ->expectsOutputToContain('--promote-existing')
            ->expectsOutputToContain('--reset-password')
            ->doesntExpectOutputToContain('--password')
            ->assertExitCode(0);
    }
}
