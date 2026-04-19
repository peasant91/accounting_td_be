<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_role_is_admin(): void
    {
        $user = User::factory()->create();
        $this->assertSame(UserRole::Admin, $user->role);
    }

    public function test_is_super_admin_true_when_role_super(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_is_super_admin_false_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->assertFalse($user->isSuperAdmin());
    }
}
