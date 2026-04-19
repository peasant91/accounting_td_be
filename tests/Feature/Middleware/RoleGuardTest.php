<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'role:super_admin'])
            ->get('/test-super-only', fn () => response()->json(['ok' => true]));
    }

    public function test_super_admin_can_access(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user)->getJson('/test-super-only')->assertOk();
    }

    public function test_regular_admin_forbidden(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/test-super-only')->assertForbidden();
    }

    public function test_unauthenticated_unauthorized(): void
    {
        $this->getJson('/test-super-only')->assertUnauthorized();
    }
}
