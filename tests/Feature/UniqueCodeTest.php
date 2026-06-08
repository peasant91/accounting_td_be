<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniqueCodeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    public function test_invoices_table_has_use_unique_code_column(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0042',
            'currency' => 'IDR',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => InvoiceStatus::Draft,
            'tax_rate' => 0,
            'subtotal' => 1000000,
            'tax_amount' => 0,
            'total' => 1000000,
            'type' => InvoiceType::Manual,
            'use_unique_code' => true,
        ]);

        $this->assertTrue($invoice->use_unique_code);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'use_unique_code' => 1]);
    }

    public function test_recurring_invoices_table_has_use_unique_code_column(): void
    {
        $customer = Customer::factory()->create();
        $ri = \App\Models\RecurringInvoice::create([
            'customer_id' => $customer->id,
            'title' => 'Test',
            'recurrence_type' => \App\Enums\RecurrenceType::Manual,
            'recurrence_interval' => 1,
            'start_date' => now()->format('Y-m-d'),
            'status' => \App\Enums\RecurringStatus::Active,
            'line_items' => [],
            'tax_rate' => 0,
            'currency' => 'IDR',
            'due_date_offset' => 7,
            'use_unique_code' => true,
        ]);

        $this->assertTrue($ri->use_unique_code);
    }

    public function test_use_unique_code_defaults_to_false(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0001',
            'currency' => 'IDR',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => InvoiceStatus::Draft,
            'tax_rate' => 0,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'type' => InvoiceType::Manual,
        ]);

        $this->assertFalse((bool) $invoice->use_unique_code);
    }

    public function test_unique_code_accessor_derives_from_invoice_number(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0042',
            'currency' => 'IDR',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => InvoiceStatus::Draft,
            'tax_rate' => 0,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'type' => InvoiceType::Manual,
            'use_unique_code' => true,
        ]);

        $this->assertEquals(42, $invoice->unique_code);
    }

    public function test_unique_code_accessor_handles_leading_zeros(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0001',
            'currency' => 'IDR',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => InvoiceStatus::Draft,
            'tax_rate' => 0,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'type' => InvoiceType::Manual,
        ]);

        $this->assertEquals(1, $invoice->unique_code);
    }

    public function test_store_invoice_accepts_use_unique_code(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'tax_rate' => 0,
            'use_unique_code' => true,
            'items' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 1000000],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['use_unique_code' => 1]);
    }

    public function test_recurring_invoice_passes_use_unique_code_when_generating(): void
    {
        $customer = Customer::factory()->create();
        $ri = \App\Models\RecurringInvoice::create([
            'customer_id' => $customer->id,
            'title' => 'Monthly',
            'recurrence_type' => \App\Enums\RecurrenceType::Manual,
            'recurrence_interval' => 1,
            'start_date' => now()->format('Y-m-d'),
            'status' => \App\Enums\RecurringStatus::Active,
            'line_items' => [['description' => 'Fee', 'quantity' => 1, 'unit_price' => 500, 'amount' => 500]],
            'tax_rate' => 0,
            'currency' => 'IDR',
            'due_date_offset' => 7,
            'use_unique_code' => true,
        ]);

        $service = app(\App\Services\RecurringInvoiceService::class);
        $invoice = $service->generateInvoice($ri, isManual: true);

        $this->assertNotNull($invoice);
        $this->assertTrue($invoice->use_unique_code);
    }

    public function test_store_invoice_use_unique_code_defaults_to_false(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'tax_rate' => 0,
            'items' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.use_unique_code', false);
    }
}
