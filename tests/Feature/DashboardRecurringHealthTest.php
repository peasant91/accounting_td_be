<?php

namespace Tests\Feature;

use App\Enums\RecurrenceType;
use App\Enums\RecurringStatus;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardRecurringHealthTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    private function schedule(array $overrides = []): RecurringInvoice
    {
        $customer = Customer::factory()->create(['currency' => 'IDR']);
        return RecurringInvoice::create(array_merge([
            'customer_id' => $customer->id,
            'title' => 'T',
            'recurrence_type' => RecurrenceType::Monthly->value,
            'recurrence_interval' => 1,
            'start_date' => now()->subDays(3)->toDateString(),
            'next_invoice_date' => now()->subDays(3)->toDateString(),
            'status' => RecurringStatus::Active->value,
            'line_items' => [],
            'tax_rate' => 0,
            'currency' => 'IDR',
        ], $overrides));
    }

    public function test_overdue_count_reflects_overdue_schedules(): void
    {
        $this->schedule();                                // overdue
        $this->schedule(['next_invoice_date' => now()->addDay()->toDateString()]);  // not overdue

        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $this->assertSame(1, $res['data']['recurring_invoices']['overdue_count']);
    }

    public function test_cron_is_silent_when_cache_missing(): void
    {
        Cache::forget('recurring_cron.last_run_at');

        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $this->assertTrue($res['data']['recurring_invoices']['cron']['is_silent']);
        $this->assertNull($res['data']['recurring_invoices']['cron']['last_run_at']);
    }

    public function test_cron_is_silent_when_last_run_before_today(): void
    {
        Cache::forever('recurring_cron.last_run_at', now()->subDays(2));

        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $this->assertTrue($res['data']['recurring_invoices']['cron']['is_silent']);
    }

    public function test_cron_not_silent_when_last_run_today(): void
    {
        Cache::forever('recurring_cron.last_run_at', now());

        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $this->assertFalse($res['data']['recurring_invoices']['cron']['is_silent']);
    }
}
