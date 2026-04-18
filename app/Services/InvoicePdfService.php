<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfService
{
    public function __construct(
        protected InvoiceTemplateService $templateService
    ) {
    }

    /**
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generate(Invoice $invoice)
    {
        $invoice->loadMissing(['items', 'customer.invoiceTemplate']);

        $templateData = $this->templateService->getTemplateForCustomer($invoice->customer);
        $locale = $templateData['resolved_locale'];

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'components' => $templateData['components'],
            'labels' => $locale['labels'],
            'language' => $locale['language'],
        ])->setPaper('a4', 'portrait');
    }

    public function generateRaw(Invoice $invoice): string
    {
        return $this->generate($invoice)->output();
    }

    public static function formatCurrency(float $amount, string $currency): string
    {
        return match ($currency) {
            'JPY' => '¥' . number_format($amount, 0, '.', '.'),
            'IDR' => 'IDR ' . number_format($amount, 0, '.', '.'),
            default => '$' . number_format($amount, 2, '.', ','),
        };
    }
}
