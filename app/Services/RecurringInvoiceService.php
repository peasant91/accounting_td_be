<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Enums\RecurrenceType;
use App\Enums\RecurrenceUnit;
use App\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecurringInvoiceService
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Create a new recurring invoice template.
     */
    public function create(array $data): RecurringInvoice
    {
        // Calculate initial next_invoice_date
        $startDate = Carbon::parse($data['start_date']);
        $nextInvoiceDate = $startDate->copy();

        // If recurrence is manual, no next date
        if (($data['recurrence_type'] ?? null) === RecurrenceType::Manual->value) {
            $nextInvoiceDate = null;
        }

        $data['next_invoice_date'] = $nextInvoiceDate;
        $data['status'] = 'active'; // Default to active? Or logic based on start date? Req 25 says "Pending" if future.

        // Logic for status:
        // Pending if start_date > today and generated_count == 0?
        // Actually, db default is 'pending'.
        // If start_date <= today, it should probably be ready to run, so 'active' is fine if we want it to be picked up.
        // But let's stick to simple state. If created, it is active (unless deactivated). 
        // The Req 25 says "Pending — the template has been created but the start_date is in the future".
        // This suggests status is derived or managed.
        // Let's set 'active' if user enables it. Usually creation implies active.

        $data['status'] = $startDate->isFuture() ? 'pending' : 'active';

        return RecurringInvoice::create($data);
    }

    /**
     * Update a recurring invoice template.
     */
    public function update(RecurringInvoice $recurringInvoice, array $data): RecurringInvoice
    {
        // If schedule fields changed, we might need to recalc next_invoice_date check?
        // Usually modifying a running schedule is tricky.
        // For simplicity, we assume user knows what they are doing.
        // If they change start_date, we might reset?
        // Let's just update fields. 
        // However, if they switch to Manual, we must clear next_invoice_date.

        $recurrenceType = $data['recurrence_type'] ?? $recurringInvoice->recurrence_type->value;

        if ($recurrenceType === RecurrenceType::Manual->value) {
            $data['next_invoice_date'] = null;
        } elseif (isset($data['start_date']) && $recurringInvoice->generated_count === 0) {
            // If no invoices generated yet, sync next_invoice_date to the new start_date
            $data['next_invoice_date'] = Carbon::parse($data['start_date']);
        }

        // Update status logic based on start_date
        if (isset($data['start_date'])) {
            $startDate = Carbon::parse($data['start_date']);
            if ($recurringInvoice->generated_count === 0) {
                $data['status'] = $startDate->isFuture() ? 'pending' : 'active';
            }
        }

        $recurringInvoice->update($data);

        return $recurringInvoice;
    }

    /**
     * Generate an invoice from the template.
     */
    public function generateInvoice(RecurringInvoice $recurringInvoice, bool $isManual = false): ?\App\Models\Invoice
    {
        if ($recurringInvoice->status === 'completed' || $recurringInvoice->status === 'terminated') {
            return null;
        }

        return DB::transaction(function () use ($recurringInvoice, $isManual) {
            // Prepared invoice data
            $invoiceDate = Carbon::now();
            $dueDate = null;

            if ($recurringInvoice->due_date_offset !== null) {
                $dueDate = $invoiceDate->copy()->addDays($recurringInvoice->due_date_offset);
            }

            $invoiceData = [
                'customer_id' => $recurringInvoice->customer_id,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'tax_rate' => $recurringInvoice->tax_rate,
                'notes' => $recurringInvoice->notes,
                'currency' => $recurringInvoice->currency,
                'type' => InvoiceType::Recurring,
                'recurring_invoice_id' => $recurringInvoice->id,
                'items' => $recurringInvoice->line_items,
            ];

            // Create Invoice
            $invoice = $this->invoiceService->create($invoiceData);

            // Update Recurring Invoice State
            if (!$isManual) {
                $this->updateAfterGeneration($recurringInvoice);
            }

            return $invoice;
        });
    }

    /**
     * Update the recurring invoice state after auto-generation.
     */
    protected function updateAfterGeneration(RecurringInvoice $recurringInvoice): void
    {
        $recurringInvoice->generated_count++;
        $recurringInvoice->last_generated_at = now();

        // Check if completed (for counted)
        if (
            $recurringInvoice->recurrence_type === RecurrenceType::Counted &&
            $recurringInvoice->total_count !== null &&
            $recurringInvoice->generated_count >= $recurringInvoice->total_count
        ) {

            $recurringInvoice->status = 'completed';
            $recurringInvoice->next_invoice_date = null;
        } else {
            // Calculate next date
            $recurringInvoice->next_invoice_date = $this->calculateNextDate($recurringInvoice);

            // If next date became active (from pending)
            if ($recurringInvoice->status === 'pending') {
                $recurringInvoice->status = 'active';
            }
        }

        $recurringInvoice->save();
    }

    /**
     * Calculate the next invoice date based on recurrence settings.
     */
    public function calculateNextDate(RecurringInvoice $recurringInvoice): Carbon
    {
        // Base calculation on the previous scheduled date (next_invoice_date) to avoid drift
        // If strictly new, usage today? 
        // Plan Requirement 44: "update the template's next_invoice_date to the next recurrence date."
        // We should use the CURRENT next_invoice_date as the base.
        $baseDate = $recurringInvoice->next_invoice_date ? $recurringInvoice->next_invoice_date->copy() : Carbon::parse($recurringInvoice->start_date);

        $interval = $recurringInvoice->recurrence_interval ?? 1;

        switch ($recurringInvoice->recurrence_type) {
            case RecurrenceType::Monthly:
                return $baseDate->addMonths($interval); // Usually interval 1 for Monthly
            case RecurrenceType::Weekly:
                return $baseDate->addWeeks($interval); // Usually interval 1
            case RecurrenceType::BiWeekly:
                return $baseDate->addWeeks(2);
            case RecurrenceType::TriWeekly:
                return $baseDate->addWeeks(3);
            case RecurrenceType::Counted:
                $unit = $recurringInvoice->recurrence_unit;
                if ($unit === RecurrenceUnit::Day)
                    return $baseDate->addDays($interval);
                if ($unit === RecurrenceUnit::Week)
                    return $baseDate->addWeeks($interval);
                if ($unit === RecurrenceUnit::Month)
                    return $baseDate->addMonths($interval);
                if ($unit === RecurrenceUnit::Year)
                    return $baseDate->addYears($interval);
                // Fallback default? Maybe month?
                return $baseDate->addMonth();
            case RecurrenceType::Manual:
                return $baseDate; // Should not happen
            default:
                return $baseDate->addMonth();
        }
    }

    /**
     * Process all scheduled invoices due today.
     */
    public function processScheduledInvoices(): int
    {
        $today = Carbon::today();

        // Find active invoices with next_invoice_date <= today
        // Also include 'pending' status if start_date reached?
        // Req: "checks all active invoice templates."
        // Let's assume 'pending' becomes 'active' effectively when date is reached.
        $query = RecurringInvoice::whereIn('status', ['active', 'pending'])
            ->where('recurrence_type', '!=', RecurrenceType::Manual)
            ->whereDate('next_invoice_date', '<=', $today);

        $count = 0;
        $invoices = $query->get();

        foreach ($invoices as $recurringInvoice) {
            try {
                $this->generateInvoice($recurringInvoice);
                $count++;
            } catch (\Exception $e) {
                Log::error("Failed to generate recurring invoice ID {$recurringInvoice->id}: " . $e->getMessage());
            }
        }

        return $count;
    }
}
