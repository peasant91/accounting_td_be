<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Exceptions\MissingRateException;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\CurrencyConverter;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        protected CurrencyConverter $converter
    ) {
    }

    public function getSummary(): array
    {
        return [
            'total_receivables' => $this->getTotalReceivables(),
            'total_customers' => $this->getTotalCustomers(),
            'invoices_due_this_month' => $this->getInvoicesDueThisMonth(),
            'recurring_invoices' => $this->getRecurringInvoicesSummary(),
            'recent_activity' => $this->getRecentActivity(),
        ];
    }

    public function getTotalReceivables(): array
    {
        $byCurrency = Invoice::unpaid()
            ->selectRaw('currency, SUM(total) as amount')
            ->groupBy('currency')
            ->get();

        $base = config('billing.base_currency', 'IDR');
        $breakdown = [];
        $missing = [];
        $baseTotal = 0.0;

        foreach ($byCurrency as $row) {
            $amount = (float) $row->amount;
            $entry = [
                'currency' => $row->currency,
                'amount' => $amount,
                'base_equivalent' => null,
            ];
            try {
                $entry['base_equivalent'] = $this->converter->convert($amount, $row->currency, $base);
                $baseTotal += $entry['base_equivalent'];
            } catch (MissingRateException) {
                $missing[] = $row->currency;
            }
            $breakdown[] = $entry;
        }

        return [
            'base_currency' => $base,
            'base_total' => $baseTotal,
            'breakdown' => $breakdown,
            'rates_updated_at' => $this->converter->ratesUpdatedAt()?->toIso8601String(),
            'missing_rates' => $missing,
        ];
    }

    public function getTotalCustomers(): int
    {
        return Customer::where('status', CustomerStatus::Active->value)->count();
    }

    public function getInvoicesDueThisMonth(): array
    {
        $row = Invoice::unpaid()
            ->whereBetween('due_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as amount')
            ->first();

        return [
            'count' => (int) ($row->count ?? 0),
            'amount' => (float) ($row->amount ?? 0),
        ];
    }

    public function getRecurringInvoicesSummary(): array
    {
        $generatedToday = Invoice::where('type', \App\Enums\InvoiceType::Recurring->value)
            ->whereDate('created_at', now()->today())
            ->count();

        $overdueCount = \App\Models\RecurringInvoice::dueForGeneration()->count();

        $lastRun = cache('recurring_cron.last_run_at');
        $cron = [
            'last_run_at' => $lastRun?->toIso8601String(),
            'is_silent' => $lastRun === null || $lastRun->lt(now()->startOfDay()),
        ];

        $today = now()->today();
        $upcoming = \App\Models\RecurringInvoice::where('status', \App\Enums\RecurringStatus::Active->value)
            ->where('recurrence_type', '!=', \App\Enums\RecurrenceType::Manual->value)
            ->where('next_invoice_date', '>=', $today->toDateString())
            ->where('next_invoice_date', '<=', $today->copy()->addDays(7)->toDateString())
            ->with('customer')
            ->orderBy('next_invoice_date')
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'customer_id' => $inv->customer_id,
                'customer_name' => $inv->customer->name,
                'title' => $inv->title,
                'next_invoice_date' => $inv->next_invoice_date?->toDateString(),
            ]);

        return [
            'generated_today' => $generatedToday,
            'overdue_count' => $overdueCount,
            'cron' => $cron,
            'upcoming' => $upcoming,
        ];
    }

    public function getRecentActivity(int $limit = 5): Collection
    {
        return ActivityLog::with('loggable')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $this->formatActivityDescription($log),
                'created_at' => $log->created_at?->toISOString(),
            ]);
    }

    private function formatActivityDescription(ActivityLog $log): string
    {
        $loggable = $log->loggable;
        $recipient = $log->properties['recipient'] ?? 'customer';

        return match ($log->action) {
            'customer.created', 'invoice.created', 'recurringinvoice.created' => $this->getCreatedDescription($loggable),
            'customer.updated', 'invoice.updated', 'recurringinvoice.updated' => $this->getUpdatedDescription($loggable),
            'customer.deleted', 'invoice.deleted', 'recurringinvoice.deleted' => $this->getDeletedDescription($log),
            'invoice.sent' => "Invoice {$loggable?->invoice_number} sent to {$recipient}",
            'invoice.reminder_sent' => "Payment reminder sent for invoice {$loggable?->invoice_number}",
            'invoice.marked_as_paid' => "Invoice {$loggable?->invoice_number} marked as paid",
            'invoice.cancelled' => "Invoice {$loggable?->invoice_number} cancelled",
            default => ucfirst(str_replace('.', ' ', $log->action)) . ' action performed',
        };
    }

    private function getCreatedDescription($loggable): string
    {
        if ($loggable instanceof Customer) {
            return "Customer {$loggable->name} created";
        }
        if ($loggable instanceof Invoice) {
            return "Invoice {$loggable->invoice_number} created";
        }
        return 'New record created';
    }

    private function getUpdatedDescription($loggable): string
    {
        if ($loggable instanceof Customer) {
            return "Customer {$loggable->name} updated";
        }
        if ($loggable instanceof Invoice) {
            return "Invoice {$loggable->invoice_number} updated";
        }
        return 'Record updated';
    }

    private function getDeletedDescription(ActivityLog $log): string
    {
        return class_basename($log->loggable_type) . ' deleted';
    }
}
