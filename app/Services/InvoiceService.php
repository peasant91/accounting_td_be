<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Enums\InvoiceType;
use App\Models\InvoiceSequence;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Get paginated list of invoices with filtering and search.
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $query = Invoice::with('customer');

        // Search by invoice number or customer name
        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->byStatus($request->status);
        }

        // Filter by customer
        if ($request->has('customer_id') && $request->customer_id) {
            $query->byCustomer($request->customer_id);
        }

        // Filter by invoice date range
        if ($request->has('date_from') || $request->has('date_to')) {
            $query->dateRange($request->date_from, $request->date_to, 'invoice_date');
        }

        // Filter by due date range
        if ($request->has('due_date_from') || $request->has('due_date_to')) {
            $query->dateRange($request->due_date_from, $request->due_date_to, 'due_date');
        }

        // Filter by type
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        // Default ordering
        $query->orderBy('created_at', 'desc');

        // Pagination
        $perPage = min((int) $request->get('per_page', 25), 100);

        return $query->paginate($perPage);
    }

    /**
     * Create a new invoice with items.
     */
    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            // Get customer currency
            $customer = Customer::findOrFail($data['customer_id']);
            $currency = $customer->currency ?? 'IDR';

            // Generate invoice number
            $year = now()->year;
            $invoiceNumber = InvoiceSequence::getNextNumber($year);

            // Create invoice
            $invoice = Invoice::create([
                'customer_id' => $data['customer_id'],
                'currency' => $currency,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'tax_rate' => $data['tax_rate'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'status' => InvoiceStatus::Draft,
                'type' => $data['type'] ?? InvoiceType::Manual,
                'recurring_invoice_id' => $data['recurring_invoice_id'] ?? null,
            ]);

            // Create items
            $this->syncItems($invoice, $data['items']);

            // Calculate totals
            $invoice->calculateTotals();

            return $invoice->load(['items', 'customer']);
        });
    }

    /**
     * Update a draft invoice.
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'customer_id' => $data['customer_id'] ?? $invoice->customer_id,
                'invoice_date' => $data['invoice_date'] ?? $invoice->invoice_date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'tax_rate' => $data['tax_rate'] ?? $invoice->tax_rate,
                'notes' => $data['notes'] ?? $invoice->notes,
                'internal_notes' => $data['internal_notes'] ?? $invoice->internal_notes,
            ]);

            // Update items if provided
            if (isset($data['items'])) {
                $this->syncItems($invoice, $data['items']);
                $invoice->calculateTotals();
            }

            return $invoice->fresh(['items', 'customer']);
        });
    }

    /**
     * Delete a draft invoice.
     */
    public function delete(Invoice $invoice): void
    {
        if (!$invoice->isDeletable()) {
            throw new \Exception('Only draft invoices can be deleted. Use cancel for sent invoices.');
        }

        $invoice->items()->delete();
        $invoice->forceDelete();
    }

    /**
     * Send invoice to customer.
     */
    public function send(Invoice $invoice, array $data): void
    {
        // Update status to sent if draft
        if ($invoice->status === InvoiceStatus::Draft) {
            $invoice->update(['status' => InvoiceStatus::Sent]);
        }

        // Log the send activity
        $invoice->logActivity('invoice_sent', [
            'recipient' => $data['recipient_email'],
            'subject' => $data['subject'],
        ]);

        // TODO: Dispatch job to send email
        // SendInvoiceEmailJob::dispatch($invoice, $data);
    }

    /**
     * Send payment reminder.
     */
    public function sendReminder(Invoice $invoice, array $data): void
    {
        $invoice->logActivity('reminder_sent', [
            'recipient' => $data['recipient_email'],
            'subject' => $data['subject'],
        ]);

        // TODO: Dispatch job to send reminder email
        // SendReminderEmailJob::dispatch($invoice, $data);
    }

    /**
     * Mark invoice as paid.
     */
    public function markAsPaid(Invoice $invoice, array $data): Invoice
    {
        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'payment_date' => $data['payment_date'],
            'payment_method' => $data['payment_method'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null,
            'payment_notes' => $data['notes'] ?? null,
        ]);

        $invoice->logActivity('marked_as_paid', [
            'payment_date' => $data['payment_date'],
            'payment_method' => $data['payment_method'] ?? null,
        ]);

        return $invoice->fresh();
    }

    /**
     * Cancel an invoice.
     */
    public function cancel(Invoice $invoice, array $data): Invoice
    {
        $invoice->update([
            'status' => InvoiceStatus::Cancelled,
            'cancellation_reason' => $data['cancellation_reason'],
        ]);

        $invoice->logActivity('cancelled', [
            'reason' => $data['cancellation_reason'],
        ]);

        return $invoice->fresh();
    }

    /**
     * Sync invoice items.
     */
    private function syncItems(Invoice $invoice, array $items): void
    {
        // Delete existing items
        $invoice->items()->delete();

        // Create new items
        foreach ($items as $index => $itemData) {
            $invoice->items()->create([
                'description' => $itemData['description'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'sort_order' => $index,
            ]);
        }
    }
}
