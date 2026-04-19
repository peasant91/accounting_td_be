<?php

namespace Tests\Feature\Services;

use App\Models\ActivityLog;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditsChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_create_logs_created_with_after(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $customer = Customer::factory()->create(['name' => 'Acme Ltd']);

        $log = ActivityLog::where('action', 'customer.created')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame($customer->id, $log->loggable_id);
        $this->assertSame('Acme Ltd', $log->properties['after']['name']);
    }

    public function test_customer_update_logs_before_and_after_changed_fields_only(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $customer = Customer::factory()->create(['name' => 'A', 'email' => 'a@x.com']);
        ActivityLog::truncate();

        $customer->update(['name' => 'B']);

        $log = ActivityLog::where('action', 'customer.updated')->latest('id')->first();
        $this->assertSame('A', $log->properties['before']['name']);
        $this->assertSame('B', $log->properties['after']['name']);
        $this->assertArrayNotHasKey('email', $log->properties['before']);
    }

    public function test_customer_delete_logs_deleted_with_before(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $customer = Customer::factory()->create(['name' => 'DeleteMe']);
        $customer->delete();

        $log = ActivityLog::where('action', 'customer.deleted')->latest('id')->first();
        $this->assertSame('DeleteMe', $log->properties['before']['name']);
    }

    public function test_user_password_is_redacted_in_diffs(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $this->actingAs($actor);

        $user = User::factory()->create();
        $user->update(['password' => 'new-secret-123']);

        $log = ActivityLog::where('action', 'user.updated')->latest('id')->first();
        $this->assertArrayHasKey('password', $log->properties['after']);
        $this->assertSame('***', $log->properties['after']['password']);
        if (array_key_exists('password', $log->properties['before'] ?? [])) {
            $this->assertSame('***', $log->properties['before']['password']);
        }
    }

    public function test_rate_update_logs_before_after(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $rate = CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);
        ActivityLog::truncate();

        $rate->update(['rate_to_base' => 16250]);

        $log = ActivityLog::where('action', 'currencyrate.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertNotEquals($log->properties['before']['rate_to_base'], $log->properties['after']['rate_to_base']);
    }
}
