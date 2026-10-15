<?php

// SetPanelAdminCommand.php — Creates or updates the panel admin user (manual only).
//
// exports: SetPanelAdminCommand | SetPanelAdminCommand::handle(): int
// used_by: none
// rules:   Never print ADMIN_PASSWORD or password option. Does not delete users or run seeds.
// agent:   composer-2.5 | cursor | 2026-10-15 | panel_admin_cli | Dokploy terminal bootstrap for admin login.

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dokhosts:set-panel-admin
    {--email= : Admin email (default: ADMIN_EMAIL env)}
    {--password= : Plain password (default: ADMIN_PASSWORD env)}')]
#[Description('Create or update the panel admin user from options or ADMIN_EMAIL / ADMIN_PASSWORD env')]
class SetPanelAdminCommand extends Command
{
    public function handle(): int
    {
        $email = $this->resolveEmail();
        $password = $this->resolvePassword();

        if ($email === null || $email === '') {
            $this->error('Admin email is required. Set ADMIN_EMAIL in Dokploy or pass --email=');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid admin email.');

            return self::FAILURE;
        }

        if ($password === null || $password === '') {
            $this->error('Admin password is required. Set ADMIN_PASSWORD in Dokploy or pass --password=');

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => $password,
                'is_admin' => true,
            ],
        );

        $action = $user->wasRecentlyCreated ? 'created' : 'updated';
        $this->info("Panel admin {$action} for {$email}.");

        return self::SUCCESS;
    }

    private function resolveEmail(): ?string
    {
        $option = $this->option('email');

        if (is_string($option) && $option !== '') {
            return $option;
        }

        $fromEnv = $this->readEnv('ADMIN_EMAIL');

        return $fromEnv !== '' ? $fromEnv : null;
    }

    private function resolvePassword(): ?string
    {
        $option = $this->option('password');

        if (is_string($option) && $option !== '') {
            return $option;
        }

        $fromEnv = $this->readEnv('ADMIN_PASSWORD');

        return $fromEnv !== '' ? $fromEnv : null;
    }

    private function readEnv(string $key): string
    {
        $value = getenv($key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (isset($_ENV[$key]) && is_string($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        return '';
    }
}
