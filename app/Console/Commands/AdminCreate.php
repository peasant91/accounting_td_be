<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class AdminCreate extends Command
{
    protected $signature = 'admin:create
        {--name= : Name}
        {--email= : Email}
        {--password= : Password (omit to be prompted)}
        {--super : Create as super_admin}';

    protected $description = 'Create a new admin user';

    public function handle(AuditLogger $auditLogger): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = strtolower($this->option('email') ?: $this->ask('Email'));
        $password = $this->option('password') ?: $this->secret('Password');
        $isSuper = (bool) $this->option('super');

        if ($isSuper && !$this->option('no-interaction')) {
            if (!$this->confirm('This will create a SUPER ADMIN with full system access. Continue?', false)) {
                $this->warn('Aborted.');
                return 1;
            }
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', \Illuminate\Validation\Rules\Password::min(12)->letters()->numbers()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $msg) {
                $this->error($msg);
            }
            return 1;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("An account with email {$email} already exists.");
            return 2;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $isSuper ? UserRole::SuperAdmin : UserRole::Admin,
        ]);

        $auditLogger->log(
            action: 'admin.created',
            target: $user,
            properties: ['via' => 'cli', 'super' => $isSuper],
            userId: null,
        );

        $this->info("Created {$user->role->value} #{$user->id} <{$user->email}>");
        return 0;
    }
}
