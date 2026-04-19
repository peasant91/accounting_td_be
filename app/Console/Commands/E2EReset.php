<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class E2EReset extends Command
{
    protected $signature = 'e2e:reset';
    protected $description = 'Reset DB and seed known admins for E2E tests (NEVER run in production)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');
            return 1;
        }

        Artisan::call('migrate:fresh', ['--force' => true]);

        User::create([
            'name' => 'Super E2E',
            'email' => 'super@e2e.test',
            'password' => 'super-password-12',
            'role' => UserRole::SuperAdmin,
        ]);
        User::create([
            'name' => 'Admin E2E',
            'email' => 'admin@e2e.test',
            'password' => 'admin-password-12',
            'role' => UserRole::Admin,
        ]);

        $this->info('E2E reset complete.');
        return 0;
    }
}
