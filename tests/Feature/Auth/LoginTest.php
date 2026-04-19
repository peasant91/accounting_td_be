<?php

namespace Tests\Feature\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_204(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('secret-password-12'),
        ]);

        $res = $this->postJson('/api/v1/login', [
            'email' => 'admin@example.com',
            'password' => 'secret-password-12',
        ]);

        $res->assertNoContent();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('login_attempts', [
            'email' => 'admin@example.com',
            'successful' => true,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'auth.login',
            'user_id' => $user->id,
        ]);
    }

    public function test_login_with_wrong_password_returns_401_and_records_failure(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('correct-password-12'),
        ]);

        $res = $this->postJson('/api/v1/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $res->assertUnauthorized();
        $this->assertDatabaseHas('login_attempts', [
            'email' => 'admin@example.com',
            'successful' => false,
            'user_id' => $user->id,
        ]);
    }

    public function test_login_with_unknown_email_returns_401_and_records_null_user(): void
    {
        $res = $this->postJson('/api/v1/login', [
            'email' => 'nobody@example.com',
            'password' => 'anything',
        ]);

        $res->assertUnauthorized();
        $this->assertDatabaseHas('login_attempts', [
            'email' => 'nobody@example.com',
            'successful' => false,
            'user_id' => null,
        ]);
    }

    public function test_login_is_rate_limited_after_5_failures(): void
    {
        User::factory()->create([
            'email' => 'target@example.com',
            'password' => Hash::make('correct-password-12'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'target@example.com',
                'password' => 'wrong',
            ])->assertUnauthorized();
        }

        $res = $this->postJson('/api/v1/login', [
            'email' => 'target@example.com',
            'password' => 'wrong',
        ]);

        $res->assertStatus(429);
        $this->assertNotNull($res->headers->get('Retry-After'));
    }
}
