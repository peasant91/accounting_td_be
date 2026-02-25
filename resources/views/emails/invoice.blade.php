<x-mail::message>
    @if($messageBody)
        {{ $messageBody }}
    @endif

    **Invoice #:** {{ $invoice->invoice_number }}
    **Date:** {{ $invoice->invoice_date->format('Y-m-d') }}
    @if($invoice->due_date)
        **Due Date:** {{ $invoice->due_date->format('Y-m-d') }}
    @endif
    **Total:** {{ $invoice->currency }} {{ number_format($invoice->total, 2) }}

    Please find the invoice attached as a PDF.

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>