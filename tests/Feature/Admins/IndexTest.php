<?php

namespace Tests\Feature\Admins;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauth_returns_401(): void
    {
        $this->getJson('/api/v1/admins')->assertUnauthorized();
    }

    public function test_regular_admin_forbidden(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->getJson('/api/v1/admins')->assertForbidden();
    }

    public function test_super_admin_sees_all(): void
    {
        $super = User::factory()->superAdmin()->create();
        User::factory()->count(3)->create();

        $res = $this->actingAs($super)->getJson('/api/v1/admins')->assertOk();
        $this->assertGreaterThanOrEqual(4, count($res->json('data')));
    }
}
