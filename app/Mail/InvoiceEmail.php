<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    private ?string $pdfContent = null;

    public function __construct(
        public Invoice $invoice,
        string $subject,
        public ?string $messageBody = null,
    ) {
        $this->subject = $subject;
        $this->invoice->loadMissing(['items', 'customer']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'messageBody' => $this->messageBody,
            ],
        );
    }

    public function attachments(): array
    {
        $this->pdfContent ??= app(InvoicePdfService::class)->generateRaw($this->invoice);
        $filename = "Invoice-{$this->invoice->invoice_number}.pdf";

        return [
            Attachment::fromData(fn () => $this->pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
