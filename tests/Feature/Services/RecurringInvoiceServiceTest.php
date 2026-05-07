<?php

namespace Tests\Feature\Services;

use App\Enums\RecurrenceType;
use App\Enums\RecurringStatus;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use App\Services\RecurringInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function activeSchedule(array $overrides = []): RecurringInvoice
    {
        $customer = Customer::factory()->create(['currency' => 'IDR']);
        return RecurringInvoice::create(array_merge([
            'customer_id' => $customer->id,
            'title' => 'Retainer',
            'recurrence_type' => RecurrenceType::Monthly->value,
            'recurrence_interval' => 1,
            'start_date' => now()->subDay()->toDateString(),
            'next_invoice_date' => now()->subDay()->toDateString(),
            'status' => RecurringStatus::Active->value,
            'line_items' => [
                ['description' => 'Svc', 'quantity' => 1, 'unit_price' => 100, 'amount' => 100],
            ],
            'tax_rate' => 0,
            'currency' => 'IDR',
        ], $overrides));
    }

    public function test_processScheduledInvoices_stamps_last_attempted_at(): void
    {
        $schedule = $this->activeSchedule();

        $count = app(RecurringInvoiceService::class)->processScheduledInvoices();

        $this->assertSame(1, $count);
        $this->assertNotNull($schedule->fresh()->last_attempted_at);
    }

    public function test_processScheduledInvoices_stamps_last_attempted_at_even_when_generation_fails(): void
    {
        $schedule = $this->activeSchedule();

        // Force generation to throw by binding an InvoiceService that fails on create().
        $this->app->bind(\App\Services\InvoiceService::class, function () {
            return new class extends \App\Services\InvoiceService {
                public function create(array $data): \App\Models\Invoice
                {
                    throw new \RuntimeException('simulated generation failure');
                }
            };
        });

        try {
            app(RecurringInvoiceService::class)->processScheduledInvoices();
        } catch (\Throwable) {
            // swallow — behaviour under test is the timestamp, not the outcome
        }

        $this->assertNotNull($schedule->fresh()->last_attempted_at);
    }

    public function test_processScheduledInvoices_propagates_item_notes_to_generated_invoice(): void
    {
        $schedule = $this->activeSchedule([
            'line_items' => [
                ['description' => 'Svc', 'notes' => 'Recurring note', 'quantity' => 1, 'unit_price' => 100, 'amount' => 100],
                ['description' => 'Other', 'quantity' => 2, 'unit_price' => 50, 'amount' => 100],
            ],
        ]);

        app(RecurringInvoiceService::class)->processScheduledInvoices();

        $invoice = $schedule->fresh()->invoices()->with('items')->latest()->first();
        $this->assertNotNull($invoice);
        $items = $invoice->items->sortBy('sort_order')->values();
        $this->assertSame('Recurring note', $items[0]->notes);
        $this->assertNull($items[1]->notes);
    }
}
