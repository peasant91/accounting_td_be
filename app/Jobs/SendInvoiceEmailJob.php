<?php

namespace App\Jobs;

use App\Mail\InvoiceEmail;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum retry attempts before marking as failed.
     */
    public int $tries = 3;

    /**
     * Backoff intervals in seconds between retries.
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public Invoice $invoice,
        public string $recipientEmail,
        public string $subject,
        public ?string $messageBody = null,
    ) {
    }

    public function handle(): void
    {
        Mail::to($this->recipientEmail)
            ->send(new InvoiceEmail(
                invoice: $this->invoice,
                subject: $this->subject,
                messageBody: $this->messageBody,
            ));
    }
}
