<?php

namespace Tests\Feature\Admins;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_deletes_another_admin(): void
    {
        $super = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($super)->deleteJson("/api/v1/admins/{$target->id}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'admin.deleted', 'user_id' => $super->id]);
    }

    public function test_cannot_delete_self(): void
    {
        $super = User::factory()->superAdmin()->create();
        $this->actingAs($super)->deleteJson("/api/v1/admins/{$super->id}")
            ->assertUnprocessable();
    }

    public function test_delete_another_super_allowed_when_more_than_one_exists(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $target = User::factory()->superAdmin()->create();

        $this->actingAs($actor)->deleteJson("/api/v1/admins/{$target->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }
}
