<?php

namespace Tests\Feature\Http\Resources;

use App\Enums\RecurrenceType;
use App\Enums\RecurringStatus;
use App\Http\Resources\RecurringInvoiceResource;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RecurringInvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_includes_is_overdue_and_last_attempted_at(): void
    {
        $customer = Customer::factory()->create(['currency' => 'IDR']);
        $schedule = RecurringInvoice::create([
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
            'last_attempted_at' => now()->subHour(),
        ]);

        $array = (new RecurringInvoiceResource($schedule))->toArray(new Request());

        $this->assertTrue($array['is_overdue']);
        $this->assertNotNull($array['last_attempted_at']);
    }
}
