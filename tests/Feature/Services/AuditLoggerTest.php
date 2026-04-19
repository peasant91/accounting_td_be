<?php

namespace Tests\Feature\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_business_event_with_target_and_user_from_auth(): void
    {
        $actor = User::factory()->create();
        $customer = Customer::factory()->create();
        $this->actingAs($actor);

        app(AuditLogger::class)->log(
            action: 'customer.updated',
            target: $customer,
            properties: ['before' => ['name' => 'A'], 'after' => ['name' => 'B']],
        );

        $log = ActivityLog::latest('id')->first();
        $this->assertSame('customer.updated', $log->action);
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame($customer->id, $log->loggable_id);
        $this->assertSame(Customer::class, $log->loggable_type);
        $this->assertSame(['before' => ['name' => 'A'], 'after' => ['name' => 'B']], $log->properties);
    }

    public function test_logs_auth_event_without_target(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        app(AuditLogger::class)->log(action: 'auth.logout');

        $log = ActivityLog::latest('id')->first();
        $this->assertSame('auth.logout', $log->action);
        $this->assertSame($user->id, $log->user_id);
        $this->assertNull($log->loggable_id);
        $this->assertNull($log->loggable_type);
    }

    public function test_captures_ip_and_user_agent_from_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        request()->server->set('REMOTE_ADDR', '10.0.0.5');
        request()->headers->set('User-Agent', 'TestBrowser/1.0');

        app(AuditLogger::class)->log(action: 'auth.login');

        $log = ActivityLog::latest('id')->first();
        $this->assertSame('10.0.0.5', $log->ip_address);
        $this->assertSame('TestBrowser/1.0', $log->user_agent);
    }

    public function test_cli_caller_has_null_user_id_and_via_cli(): void
    {
        app(AuditLogger::class)->log(
            action: 'admin.created',
            properties: ['via' => 'cli', 'super' => true],
        );

        $log = ActivityLog::latest('id')->first();
        $this->assertNull($log->user_id);
        $this->assertSame('cli', $log->properties['via']);
    }

    public function test_allows_explicit_user_id(): void
    {
        $actor = User::factory()->create();

        app(AuditLogger::class)->log(
            action: 'auth.login',
            userId: $actor->id,
        );

        $log = ActivityLog::latest('id')->first();
        $this->assertSame($actor->id, $log->user_id);
    }
}
