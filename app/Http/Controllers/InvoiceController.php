<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\CancelInvoiceRequest;
use App\Http\Requests\Invoice\MarkAsPaidRequest;
use App\Http\Requests\Invoice\SendInvoiceRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceCollection;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {
    }

    /**
     * GET /invoices - List all invoices with pagination and filters.
     */
    public function index(Request $request): InvoiceCollection
    {
        $invoices = $this->invoiceService->list($request);

        return new InvoiceCollection($invoices);
    }

    /**
     * POST /invoices - Create a new invoice.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create($request->validated());

        return response()->json([
            'data' => new InvoiceResource($invoice),
            'message' => 'Invoice created successfully',
        ], 201);
    }

    /**
     * GET /invoices/{invoice} - Get invoice details.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['items', 'customer']);

        return response()->json([
            'data' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * PUT /invoices/{invoice} - Update a draft invoice.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoiceService->update($invoice, $request->validated());

        return response()->json([
            'data' => new InvoiceResource($invoice),
            'message' => 'Invoice updated successfully',
        ]);
    }

    /**
     * DELETE /invoices/{invoice} - Delete a draft invoice.
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        try {
            $this->invoiceService->delete($invoice);

            return response()->json([
                'message' => 'Invoice deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * POST /invoices/{invoice}/send - Send invoice to customer.
     */
    public function send(SendInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->invoiceService->send($invoice, $request->validated());

        return response()->json([
            'message' => 'Invoice sent successfully',
        ]);
    }

    /**
     * POST /invoices/{invoice}/resend - Resend invoice.
     */
    public function resend(SendInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->invoiceService->send($invoice, $request->validated());

        return response()->json([
            'message' => 'Invoice resent successfully',
        ]);
    }

    /**
     * POST /invoices/{invoice}/send-reminder - Send payment reminder.
     */
    public function sendReminder(SendInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->invoiceService->sendReminder($invoice, $request->validated());

        return response()->json([
            'message' => 'Payment reminder sent successfully',
        ]);
    }

    /**
     * POST /invoices/{invoice}/mark-as-paid - Mark invoice as paid.
     */
    public function markAsPaid(MarkAsPaidRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoiceService->markAsPaid($invoice, $request->validated());

        return response()->json([
            'data' => new InvoiceResource($invoice),
            'message' => 'Invoice marked as paid',
        ]);
    }

    /**
     * POST /invoices/{invoice}/cancel - Cancel invoice.
     */
    public function cancel(CancelInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoiceService->cancel($invoice, $request->validated());

        return response()->json([
            'data' => new InvoiceResource($invoice),
            'message' => 'Invoice cancelled successfully',
        ]);
    }

    /**
     * GET /invoices/{invoice}/pdf - Download invoice PDF.
     */
    public function downloadPdf(Invoice $invoice)
    {
        $pdfService = app(InvoicePdfService::class);
        $pdf = $pdfService->generate($invoice);
        $filename = "Invoice-{$invoice->invoice_number}.pdf";

        return $pdf->download($filename);
    }
}
