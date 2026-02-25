<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfService
{
    /**
     * Generate a PDF for the given invoice.
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generate(Invoice $invoice)
    {
        $invoice->load(['items', 'customer']);

        // Resolve the customer's invoice template (or use defaults)
        $template = InvoiceTemplate::where('customer_id', $invoice->customer_id)->first();
        $components = $template
            ? $template->components
            : config('invoice.default_components');

        // Resolve locale labels based on currency
        $currency = $invoice->currency ?? 'IDR';
        $localeMap = config('invoice.currency_locale_map');
        $language = $localeMap[$currency]['language'] ?? 'en';
        $labels = config("invoice.labels.{$language}", config('invoice.labels.en'));

        $data = [
            'invoice' => $invoice,
            'components' => $components,
            'labels' => $labels,
            'language' => $language,
        ];

        return Pdf::loadView('pdf.invoice', $data)
            ->setPaper('a4', 'portrait');
    }

    /**
     * Generate PDF and return raw content as string.
     */
    public function generateRaw(Invoice $invoice): string
    {
        return $this->generate($invoice)->output();
    }

    /**
     * Format a currency amount for display in PDF.
     *
     * Matches the frontend InvoicePrintView.tsx formatting logic:
     * - JPY: ¥ + period separators, no decimals
     * - IDR: "IDR " + period separators, no decimals
     * - USD/AUD/default: $ + comma separators, 2 decimals
     */
    public static function formatCurrency(float $amount, string $currency): string
    {
        return match ($currency) {
            'JPY' => '¥' . number_format($amount, 0, '.', '.'),
            'IDR' => 'IDR ' . number_format($amount, 0, '.', '.'),
            default => '$' . number_format($amount, 2, '.', ','),
        };
    }
}
