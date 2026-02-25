<?php

namespace Tests\Unit;

use App\Mail\InvoiceEmail;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    private function createInvoice(array $overrides = []): Invoice
    {
        $customer = Customer::factory()->create();

        return Invoice::create(array_merge([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-TEST-EMAIL',
            'currency' => 'IDR',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
            'status' => \App\Enums\InvoiceStatus::Sent,
            'tax_rate' => 0,
            'subtotal' => 500000,
            'tax_amount' => 0,
            'total' => 500000,
            'type' => \App\Enums\InvoiceType::Manual,
        ], $overrides));
    }

    public function test_mailable_has_correct_subject(): void
    {
        $invoice = $this->createInvoice();

        $mailable = new InvoiceEmail(
            invoice: $invoice,
            subject: 'Invoice #INV-TEST-EMAIL',
        );

        $mailable->assertHasSubject('Invoice #INV-TEST-EMAIL');
    }

    public function test_mailable_renders_invoice_data(): void
    {
        $invoice = $this->createInvoice();
        $invoice->load(['items', 'customer']);

        $mailable = new InvoiceEmail(
            invoice: $invoice,
            subject: 'Test Invoice',
            messageBody: 'Here is your invoice.',
        );

        $rendered = $mailable->render();

        $this->assertStringContainsString('INV-TEST-EMAIL', $rendered);
    }

    public function test_mailable_includes_message_body(): void
    {
        $invoice = $this->createInvoice();

        $mailable = new InvoiceEmail(
            invoice: $invoice,
            subject: 'Test',
            messageBody: 'Please review and pay promptly.',
        );

        $rendered = $mailable->render();

        $this->assertStringContainsString('Please review and pay promptly.', $rendered);
    }

    public function test_mailable_handles_null_message_body(): void
    {
        $invoice = $this->createInvoice();

        $mailable = new InvoiceEmail(
            invoice: $invoice,
            subject: 'Test',
            messageBody: null,
        );

        // Should render without errors
        $rendered = $mailable->render();

        $this->assertStringContainsString('INV-TEST-EMAIL', $rendered);
    }

    public function test_mailable_has_pdf_attachment(): void
    {
        $invoice = $this->createInvoice();

        $mailable = new InvoiceEmail(
            invoice: $invoice,
            subject: 'Test',
        );

        $attachments = $mailable->attachments();
        $this->assertCount(1, $attachments);
        $this->assertInstanceOf(\Illuminate\Mail\Attachment::class, $attachments[0]);
    }
}
