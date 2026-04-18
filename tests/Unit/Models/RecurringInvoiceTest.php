<?php

namespace Tests\Unit\Models;

use App\Enums\RecurrenceType;
use App\Enums\RecurringStatus;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $attrs = []): RecurringInvoice
    {
        $customer = Customer::factory()->create(['currency' => 'IDR']);
        return RecurringInvoice::create(array_merge([
            'customer_id' => $customer->id,
            'title' => 'T',
            'recurrence_type' => RecurrenceType::Monthly->value,
            'recurrence_interval' => 1,
            'start_date' => now()->subDays(2)->toDateString(),
            'next_invoice_date' => now()->subDays(2)->toDateString(),
            'status' => RecurringStatus::Active->value,
            'line_items' => [],
            'tax_rate' => 0,
            'currency' => 'IDR',
        ], $attrs));
    }

    public function test_overdue_when_next_date_past_and_status_active(): void
    {
        $this->assertTrue($this->make()->isOverdue());
    }

    public function test_overdue_when_status_pending_and_next_date_past(): void
    {
        $this->assertTrue($this->make(['status' => RecurringStatus::Pending->value])->isOverdue());
    }

    public function test_not_overdue_when_next_date_today(): void
    {
        $this->assertFalse($this->make(['next_invoice_date' => now()->toDateString()])->isOverdue());
    }

    public function test_not_overdue_when_next_date_future(): void
    {
        $this->assertFalse($this->make(['next_invoice_date' => now()->addDay()->toDateString()])->isOverdue());
    }

    public function test_not_overdue_when_manual(): void
    {
        $this->assertFalse(
            $this->make([
                'recurrence_type' => RecurrenceType::Manual->value,
                'next_invoice_date' => null,
            ])->isOverdue()
        );
    }

    public function test_not_overdue_when_completed(): void
    {
        $this->assertFalse($this->make(['status' => RecurringStatus::Completed->value])->isOverdue());
    }

    public function test_not_overdue_when_terminated(): void
    {
        $this->assertFalse($this->make(['status' => RecurringStatus::Terminated->value])->isOverdue());
    }

    public function test_last_attempted_at_is_fillable_and_cast_to_carbon(): void
    {
        $ts = now()->subHour();
        $row = $this->make(['last_attempted_at' => $ts]);
        $this->assertEquals($ts->toIso8601String(), $row->fresh()->last_attempted_at->toIso8601String());
    }
}
