<?php

namespace Tests\Feature\Admins;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_updates_admin_name(): void
    {
        $super = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['name' => 'Old']);

        $this->actingAs($super)->putJson("/api/v1/admins/{$target->id}", ['name' => 'New'])
            ->assertOk();

        $this->assertSame('New', $target->fresh()->name);
    }

    public function test_role_change_logs_role_changed(): void
    {
        $super = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($super)->putJson("/api/v1/admins/{$target->id}", ['role' => 'super_admin'])
            ->assertOk();

        $this->assertSame(UserRole::SuperAdmin, $target->fresh()->role);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin.role_changed',
            'user_id' => $super->id,
            'loggable_id' => $target->id,
        ]);
    }

    public function test_blank_password_does_not_change(): void
    {
        $super = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['password' => Hash::make('original-pw-1234')]);

        $this->actingAs($super)->putJson("/api/v1/admins/{$target->id}", ['name' => 'X'])
            ->assertOk();

        $this->assertTrue(Hash::check('original-pw-1234', $target->fresh()->password));
    }

    public function test_self_demote_blocked(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->putJson("/api/v1/admins/{$super->id}", ['role' => 'admin'])
            ->assertUnprocessable();
    }

    public function test_demoting_a_super_is_allowed_when_another_super_exists(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $target = User::factory()->superAdmin()->create();

        $this->actingAs($actor)->putJson("/api/v1/admins/{$target->id}", ['role' => 'admin'])
            ->assertOk();

        $this->assertSame(UserRole::Admin, $target->fresh()->role);
    }
}
