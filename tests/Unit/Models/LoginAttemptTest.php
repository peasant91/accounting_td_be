<?php

namespace Tests\Unit\Models;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_persist_successful_attempt(): void
    {
        $user = User::factory()->create();

        $attempt = LoginAttempt::create([
            'email' => $user->email,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'successful' => true,
            'attempted_at' => now(),
        ]);

        $this->assertTrue($attempt->successful);
        $this->assertSame($user->id, $attempt->user->id);
    }

    public function test_can_persist_unknown_email_attempt(): void
    {
        $attempt = LoginAttempt::create([
            'email' => 'nobody@example.com',
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => null,
            'successful' => false,
            'attempted_at' => now(),
        ]);

        $this->assertNull($attempt->user_id);
        $this->assertFalse($attempt->successful);
    }
}
