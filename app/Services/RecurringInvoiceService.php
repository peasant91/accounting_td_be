<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Enums\RecurrenceType;
use App\Enums\RecurrenceUnit;
use App\Enums\RecurringStatus;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecurringInvoiceService
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {
    }

    public function create(array $data): RecurringInvoice
    {
        $startDate = Carbon::parse($data['start_date']);
        $isManual = ($data['recurrence_type'] ?? null) === RecurrenceType::Manual->value;

        $data['next_invoice_date'] = $isManual ? null : $startDate;
        $data['status'] = $startDate->isFuture()
            ? RecurringStatus::Pending->value
            : RecurringStatus::Active->value;

        return RecurringInvoice::create($data);
    }

    public function update(RecurringInvoice $recurringInvoice, array $data): RecurringInvoice
    {
        $recurrenceType = $data['recurrence_type']
            ?? $recurringInvoice->recurrence_type?->value;

        if ($recurrenceType === RecurrenceType::Manual->value) {
            $data['next_invoice_date'] = null;
        } elseif (isset($data['start_date']) && $recurringInvoice->generated_count === 0) {
            $data['next_invoice_date'] = Carbon::parse($data['start_date']);
        }

        if (isset($data['start_date']) && $recurringInvoice->generated_count === 0) {
            $startDate = Carbon::parse($data['start_date']);
            $data['status'] = $startDate->isFuture()
                ? RecurringStatus::Pending->value
                : RecurringStatus::Active->value;
        }

        $recurringInvoice->update($data);

        return $recurringInvoice;
    }

    public function terminate(RecurringInvoice $recurringInvoice): RecurringInvoice
    {
        $recurringInvoice->update([
            'status' => RecurringStatus::Terminated->value,
            'next_invoice_date' => null,
        ]);

        return $recurringInvoice;
    }

    public function generateInvoice(RecurringInvoice $recurringInvoice, bool $isManual = false): ?Invoice
    {
        return DB::transaction(function () use ($recurringInvoice, $isManual) {
            $locked = RecurringInvoice::whereKey($recurringInvoice->id)
                ->lockForUpdate()
                ->first();

            if (!$locked || in_array($locked->status, [RecurringStatus::Completed, RecurringStatus::Terminated], true)) {
                return null;
            }

            $invoiceDate = Carbon::now();
            $dueDate = $locked->due_date_offset !== null
                ? $invoiceDate->copy()->addDays($locked->due_date_offset)
                : null;

            $invoice = $this->invoiceService->create([
                'customer_id' => $locked->customer_id,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'tax_rate' => $locked->tax_rate,
                'notes' => $locked->notes,
                'currency' => $locked->currency,
                'type' => InvoiceType::Recurring,
                'recurring_invoice_id' => $locked->id,
                'items' => $locked->line_items,
            ]);

            if (!$isManual) {
                $this->updateAfterGeneration($locked);
            }

            return $invoice;
        });
    }

    protected function updateAfterGeneration(RecurringInvoice $recurringInvoice): void
    {
        $newGeneratedCount = ($recurringInvoice->generated_count ?? 0) + 1;
        $isCounted = $recurringInvoice->recurrence_type === RecurrenceType::Counted;
        $isFinalCount = $isCounted
            && $recurringInvoice->total_count !== null
            && $newGeneratedCount >= $recurringInvoice->total_count;

        $updates = [
            'generated_count' => DB::raw('generated_count + 1'),
            'last_generated_at' => now(),
        ];

        if ($isFinalCount) {
            $updates['status'] = RecurringStatus::Terminated->value === $recurringInvoice->status?->value
                ? RecurringStatus::Terminated->value
                : RecurringStatus::Completed->value;
            $updates['next_invoice_date'] = null;
        } else {
            $updates['next_invoice_date'] = $this->calculateNextDate($recurringInvoice);
            if ($recurringInvoice->status === RecurringStatus::Pending) {
                $updates['status'] = RecurringStatus::Active->value;
            }
        }

        RecurringInvoice::whereKey($recurringInvoice->id)->update($updates);
    }

    public function calculateNextDate(RecurringInvoice $recurringInvoice): Carbon
    {
        $baseDate = $recurringInvoice->next_invoice_date
            ? $recurringInvoice->next_invoice_date->copy()
            : Carbon::parse($recurringInvoice->start_date);

        $interval = $recurringInvoice->recurrence_interval ?? 1;

        return match ($recurringInvoice->recurrence_type) {
            RecurrenceType::Monthly => $baseDate->addMonths($interval),
            RecurrenceType::Weekly => $baseDate->addWeeks($interval),
            RecurrenceType::BiWeekly => $baseDate->addWeeks(2),
            RecurrenceType::TriWeekly => $baseDate->addWeeks(3),
            RecurrenceType::Counted => $this->addCountedInterval($baseDate, $recurringInvoice->recurrence_unit, $interval),
            default => $baseDate,
        };
    }

    private function addCountedInterval(Carbon $base, ?RecurrenceUnit $unit, int $interval): Carbon
    {
        return match ($unit) {
            RecurrenceUnit::Day => $base->addDays($interval),
            RecurrenceUnit::Week => $base->addWeeks($interval),
            RecurrenceUnit::Month => $base->addMonths($interval),
            RecurrenceUnit::Year => $base->addYears($interval),
            default => $base->addMonths($interval),
        };
    }

    public function processScheduledInvoices(?Carbon $asOf = null): int
    {
        $count = 0;

        RecurringInvoice::dueForGeneration($asOf)
            ->chunkById(100, function ($chunk) use (&$count) {
                foreach ($chunk as $recurringInvoice) {
                    try {
                        if ($this->generateInvoice($recurringInvoice)) {
                            $count++;
                        }
                    } catch (\Throwable $e) {
                        Log::error("Failed to generate recurring invoice ID {$recurringInvoice->id}: " . $e->getMessage());
                    }
                }
            });

        return $count;
    }
}
