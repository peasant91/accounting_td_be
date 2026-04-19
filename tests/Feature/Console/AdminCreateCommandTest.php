<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCreateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_admin_with_flags(): void
    {
        $this->artisan('admin:create', [
            '--name' => 'Alice',
            '--email' => 'alice@x.com',
            '--password' => 'aBcDefGh1234',
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@x.com',
            'role' => UserRole::Admin->value,
        ]);
    }

    public function test_super_flag_creates_super_admin(): void
    {
        $this->artisan('admin:create', [
            '--name' => 'Sue',
            '--email' => 'sue@x.com',
            '--password' => 'aBcDefGh1234',
            '--super' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => 'sue@x.com',
            'role' => UserRole::SuperAdmin->value,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin.created',
            'user_id' => null,
        ]);
    }

    public function test_duplicate_email_exits_nonzero(): void
    {
        User::factory()->create(['email' => 'dup@x.com']);

        $this->artisan('admin:create', [
            '--name' => 'X',
            '--email' => 'dup@x.com',
            '--password' => 'aBcDefGh1234',
            '--no-interaction' => true,
        ])->assertExitCode(2);
    }

    public function test_weak_password_exits_nonzero(): void
    {
        $this->artisan('admin:create', [
            '--name' => 'X',
            '--email' => 'x@x.com',
            '--password' => 'short',
            '--no-interaction' => true,
        ])->assertExitCode(1);
    }
}
