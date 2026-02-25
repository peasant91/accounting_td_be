<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createInvoice(array $overrides = []): Invoice
    {
        $customer = Customer::factory()->create();

        return Invoice::create(array_merge([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-CTRL-001',
            'currency' => 'IDR',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => InvoiceStatus::Sent,
            'tax_rate' => 0,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'type' => InvoiceType::Manual,
        ], $overrides));
    }

    public function test_mark_as_paid_without_file(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-001',
            'currency' => 'IDR',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => InvoiceStatus::Sent,
            'tax_rate' => 0,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'type' => InvoiceType::Manual,
        ]);

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/mark-as-paid", [
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->payment_proof_path);
    }

    public function test_mark_as_paid_with_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $customer = Customer::factory()->create();
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-002',
            'currency' => 'IDR',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => InvoiceStatus::Sent,
            'tax_rate' => 0,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'type' => InvoiceType::Manual,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('proof.pdf', 1000, 'application/pdf');

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/mark-as-paid", [
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'bank_transfer',
            'payment_proof' => $file,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->payment_proof_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($invoice->fresh()->payment_proof_path);
    }

    public function test_send_dispatches_email_job(): void
    {
        Queue::fake();

        $invoice = $this->createInvoice(['status' => InvoiceStatus::Draft]);

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/send", [
            'recipient_email' => 'customer@example.com',
            'subject' => 'Invoice #INV-CTRL-001',
            'message' => 'Please pay this invoice.',
        ]);

        $response->assertStatus(200);

        Queue::assertPushed(SendInvoiceEmailJob::class, function (SendInvoiceEmailJob $job) use ($invoice) {
            return $job->invoice->id === $invoice->id
                && $job->recipientEmail === 'customer@example.com'
                && $job->subject === 'Invoice #INV-CTRL-001'
                && $job->messageBody === 'Please pay this invoice.';
        });
    }

    public function test_resend_dispatches_email_job(): void
    {
        Queue::fake();

        $invoice = $this->createInvoice();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/resend", [
            'recipient_email' => 'customer@example.com',
            'subject' => 'Resending Invoice',
        ]);

        $response->assertStatus(200);

        Queue::assertPushed(SendInvoiceEmailJob::class, function (SendInvoiceEmailJob $job) use ($invoice) {
            return $job->invoice->id === $invoice->id
                && $job->recipientEmail === 'customer@example.com';
        });
    }

    public function test_send_reminder_dispatches_email_job(): void
    {
        Queue::fake();

        $invoice = $this->createInvoice();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/send-reminder", [
            'recipient_email' => 'customer@example.com',
            'subject' => 'Payment Reminder',
            'message' => 'Gentle reminder to pay.',
        ]);

        $response->assertStatus(200);

        Queue::assertPushed(SendInvoiceEmailJob::class, function (SendInvoiceEmailJob $job) use ($invoice) {
            return $job->invoice->id === $invoice->id
                && $job->subject === 'Payment Reminder';
        });
    }

    public function test_download_pdf_returns_pdf_response(): void
    {
        $invoice = $this->createInvoice();

        $invoice->items()->create([
            'description' => 'Test Service',
            'quantity' => 1,
            'unit_price' => 100,
            'sort_order' => 0,
        ]);

        $response = $this->get("/api/v1/invoices/{$invoice->id}/pdf");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }
}
