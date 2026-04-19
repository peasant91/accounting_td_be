<?php

namespace Tests\Feature\Audit;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAttemptsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauth_denied(): void
    {
        $this->getJson('/api/v1/audit/login-attempts')->assertUnauthorized();
    }

    public function test_filter_by_email(): void
    {
        $u = User::factory()->create();
        LoginAttempt::create(['email' => 'a@x.com', 'ip_address' => '1.1.1.1', 'successful' => true, 'attempted_at' => now()]);
        LoginAttempt::create(['email' => 'b@x.com', 'ip_address' => '1.1.1.1', 'successful' => false, 'attempted_at' => now()]);

        $res = $this->actingAs($u)->getJson('/api/v1/audit/login-attempts?email=a@x.com')->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_filter_by_successful(): void
    {
        $u = User::factory()->create();
        LoginAttempt::create(['email' => 'a@x.com', 'ip_address' => '1.1.1.1', 'successful' => true, 'attempted_at' => now()]);
        LoginAttempt::create(['email' => 'b@x.com', 'ip_address' => '1.1.1.1', 'successful' => false, 'attempted_at' => now()]);

        $res = $this->actingAs($u)->getJson('/api/v1/audit/login-attempts?successful=false')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertFalse($res->json('data.0.successful'));
    }
}
