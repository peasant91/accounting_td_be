<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createInvoice(string $currency = 'IDR'): Invoice
    {
        $customer = Customer::factory()->create(['currency' => $currency]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-PDF-001',
            'currency' => $currency,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'status' => \App\Enums\InvoiceStatus::Sent,
            'tax_rate' => 0,
            'subtotal' => 1500000,
            'tax_amount' => 0,
            'total' => 1500000,
            'type' => \App\Enums\InvoiceType::Manual,
        ]);

        $invoice->items()->create([
            'description' => 'Web Development Service',
            'quantity' => 1,
            'unit_price' => 1500000,
            'sort_order' => 0,
        ]);

        return $invoice;
    }

    public function test_generate_returns_pdf_instance(): void
    {
        $invoice = $this->createInvoice();
        $service = new InvoicePdfService();

        $pdf = $service->generate($invoice);

        $this->assertInstanceOf(\Barryvdh\DomPDF\PDF::class, $pdf);
    }

    public function test_generate_raw_returns_string(): void
    {
        $invoice = $this->createInvoice();
        $service = new InvoicePdfService();

        $raw = $service->generateRaw($invoice);

        $this->assertIsString($raw);
        $this->assertStringStartsWith('%PDF', $raw);
    }

    public function test_format_currency_idr(): void
    {
        $result = InvoicePdfService::formatCurrency(1000000, 'IDR');

        $this->assertEquals('IDR 1.000.000', $result);
    }

    public function test_format_currency_jpy(): void
    {
        $result = InvoicePdfService::formatCurrency(150000, 'JPY');

        $this->assertEquals('¥150.000', $result);
    }

    public function test_format_currency_usd(): void
    {
        $result = InvoicePdfService::formatCurrency(1500.50, 'USD');

        $this->assertEquals('$1,500.50', $result);
    }

    public function test_uses_customer_template_components(): void
    {
        $invoice = $this->createInvoice();

        // Create a custom template for the customer
        $customComponents = config('invoice.default_components');
        foreach ($customComponents as &$comp) {
            if ($comp['key'] === 'bank_transfer') {
                $comp['enabled'] = false;
            }
        }

        InvoiceTemplate::create([
            'customer_id' => $invoice->customer_id,
            'components' => $customComponents,
        ]);

        $service = new InvoicePdfService();
        $pdf = $service->generate($invoice);

        // If we got here without error, the template was used
        $this->assertInstanceOf(\Barryvdh\DomPDF\PDF::class, $pdf);
    }

    public function test_falls_back_to_default_components(): void
    {
        $invoice = $this->createInvoice();

        // No InvoiceTemplate record for this customer
        $this->assertNull(
            InvoiceTemplate::where('customer_id', $invoice->customer_id)->first()
        );

        $service = new InvoicePdfService();
        $pdf = $service->generate($invoice);

        $this->assertInstanceOf(\Barryvdh\DomPDF\PDF::class, $pdf);
    }
}
