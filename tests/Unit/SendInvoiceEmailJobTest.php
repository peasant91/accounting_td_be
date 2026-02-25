<?php

namespace Tests\Unit;

use App\Jobs\SendInvoiceEmailJob;
use App\Mail\InvoiceEmail;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendInvoiceEmailJobTest extends TestCase
{
    use RefreshDatabase;

    private function createInvoice(): Invoice
    {
        $customer = Customer::factory()->create();

        return Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-TEST-001',
            'currency' => 'IDR',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => \App\Enums\InvoiceStatus::Sent,
            'tax_rate' => 0,
            'subtotal' => 1000000,
            'tax_amount' => 0,
            'total' => 1000000,
            'type' => \App\Enums\InvoiceType::Manual,
        ]);
    }

    public function test_job_sends_email_via_mailable(): void
    {
        Mail::fake();

        $invoice = $this->createInvoice();

        $job = new SendInvoiceEmailJob(
            invoice: $invoice,
            recipientEmail: 'customer@example.com',
            subject: 'Invoice #INV-TEST-001',
            messageBody: 'Please pay this invoice.',
        );

        $job->handle();

        Mail::assertSent(InvoiceEmail::class, function (InvoiceEmail $mail) {
            return $mail->hasTo('customer@example.com');
        });
    }

    public function test_job_retries_on_failure(): void
    {
        $invoice = $this->createInvoice();

        $job = new SendInvoiceEmailJob(
            invoice: $invoice,
            recipientEmail: 'customer@example.com',
            subject: 'Test Subject',
        );

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([10, 60, 300], $job->backoff);
    }

    public function test_job_passes_correct_data_to_mailable(): void
    {
        Mail::fake();

        $invoice = $this->createInvoice();

        $job = new SendInvoiceEmailJob(
            invoice: $invoice,
            recipientEmail: 'test@example.com',
            subject: 'Custom Subject Line',
            messageBody: 'Custom message body',
        );

        $job->handle();

        Mail::assertSent(InvoiceEmail::class, function (InvoiceEmail $mail) use ($invoice) {
            return $mail->invoice->id === $invoice->id
                && $mail->subject === 'Custom Subject Line'
                && $mail->messageBody === 'Custom message body';
        });
    }
}
