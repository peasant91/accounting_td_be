<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_me_returns_user_fields(): void
    {
        $user = User::factory()->superAdmin()->create([
            'name' => 'Super',
            'email' => 'super@example.com',
        ]);
        $this->actingAs($user);

        $res = $this->getJson('/api/v1/me')->assertOk()->json();

        $this->assertSame('Super', $res['data']['name']);
        $this->assertSame('super@example.com', $res['data']['email']);
        $this->assertSame(UserRole::SuperAdmin->value, $res['data']['role']);
    }
}
