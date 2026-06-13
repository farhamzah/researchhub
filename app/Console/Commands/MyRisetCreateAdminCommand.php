<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class MyRisetCreateAdminCommand extends Command
{
    protected $signature = 'myriset:create-admin
        {--email= : Admin email address}
        {--name= : Admin display name}
        {--promote-existing : Assign super_admin to an existing user without changing the password}
        {--reset-password : Reset an existing user password after hidden confirmation prompts}';

    protected $description = 'Create or safely promote a CLI-only MyRiset super admin without printing credentials.';

    public function handle(): int
    {
        $environment = (string) config('app.env');
        $this->line('Current environment: '.$environment);

        if ($environment === 'production' && ! $this->confirm('You are creating an admin in production. Continue?')) {
            $this->warn('[WARN] Admin creation cancelled.');

            return self::FAILURE;
        }

        $email = $this->resolveEmail();
        if ($email === null) {
            return self::FAILURE;
        }

        $role = Role::query()
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->first();

        if (! $role) {
            $this->error('[FAIL] Role super_admin does not exist. Run the role/permission seeder first.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            return $this->handleExistingUser($user);
        }

        $name = $this->resolveName();
        if ($name === null) {
            return self::FAILURE;
        }

        $password = $this->resolvePassword();
        if ($password === null) {
            return self::FAILURE;
        }

        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->email_verified_at = now();
        $user->save();

        $user->assignRole('super_admin');

        $this->info('[PASS] Admin user created.');
        $this->line('Email: '.$user->email);
        $this->line('Role: super_admin');

        return self::SUCCESS;
    }

    private function handleExistingUser(User $user): int
    {
        $promoteExisting = (bool) $this->option('promote-existing');
        $resetPassword = (bool) $this->option('reset-password');

        if (! $promoteExisting && ! $resetPassword) {
            $this->warn('[WARN] User already exists. No password was changed.');
            $this->line('Use --promote-existing to assign super_admin role, or --reset-password to set a new password.');

            return self::SUCCESS;
        }

        if ($promoteExisting) {
            $user->assignRole('super_admin');
            $this->info('[PASS] Existing user promoted.');
            $this->line('Role: super_admin');
        }

        if ($resetPassword) {
            $password = $this->resolvePassword();
            if ($password === null) {
                return self::FAILURE;
            }

            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            $this->info('[PASS] Existing user password reset.');
        }

        $this->line('Email: '.$user->email);

        return self::SUCCESS;
    }

    private function resolveEmail(): ?string
    {
        $email = trim((string) ($this->option('email') ?: $this->ask('Admin email')));

        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'email:rfc'],
        ]);

        if ($validator->fails()) {
            $this->error('[FAIL] Admin email must be a valid email address.');

            return null;
        }

        return strtolower($email);
    }

    private function resolveName(): ?string
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Admin name')));

        $validator = Validator::make(['name' => $name], [
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->error('[FAIL] Admin name is required.');

            return null;
        }

        return $name;
    }

    private function resolvePassword(): ?string
    {
        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');

        if (! hash_equals($password, $confirmation)) {
            $this->error('[FAIL] Password confirmation does not match.');

            return null;
        }

        $validator = Validator::make(['password' => $password], [
            'password' => [
                'required',
                Password::min(12)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $lower = strtolower((string) $value);
                    $obvious = [
                        'password',
                        'myriset',
                        'admin',
                        'researchhub',
                        'qwerty',
                        '123456',
                    ];

                    foreach ($obvious as $term) {
                        if (str_contains($lower, $term)) {
                            $fail('The password contains an obvious weak term.');

                            return;
                        }
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            $this->error('[FAIL] Password must be at least 12 characters and include upper/lowercase letters, numbers, symbols, and no obvious weak terms.');

            return null;
        }

        return $password;
    }
}
