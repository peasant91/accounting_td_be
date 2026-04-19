<?php

namespace Tests\Feature\Admins;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_admin_forbidden(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/api/v1/admins', [
            'name' => 'X', 'email' => 'x@y.com', 'password' => 'aBcDefGh1234', 'role' => 'admin',
        ])->assertForbidden();
    }

    public function test_super_creates_admin(): void
    {
        $super = User::factory()->superAdmin()->create();

        $res = $this->actingAs($super)->postJson('/api/v1/admins', [
            'name' => 'New Admin',
            'email' => 'new@admin.com',
            'password' => 'aBcDefGh1234',
            'role' => 'admin',
        ])->assertCreated();

        $this->assertSame('new@admin.com', $res->json('data.email'));
        $this->assertDatabaseHas('users', ['email' => 'new@admin.com', 'role' => UserRole::Admin->value]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'admin.created', 'user_id' => $super->id]);
    }

    public function test_duplicate_email_422(): void
    {
        $super = User::factory()->superAdmin()->create();
        User::factory()->create(['email' => 'taken@x.com']);

        $this->actingAs($super)->postJson('/api/v1/admins', [
            'name' => 'X', 'email' => 'taken@x.com', 'password' => 'aBcDefGh1234', 'role' => 'admin',
        ])->assertUnprocessable();
    }

    public function test_weak_password_422(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->postJson('/api/v1/admins', [
            'name' => 'X', 'email' => 'x@y.com', 'password' => 'short', 'role' => 'admin',
        ])->assertUnprocessable();
    }
}
