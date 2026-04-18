<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Collection;

class DashboardService
{
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

    public function getTotalReceivables(): float
    {
        return (float) Invoice::unpaid()->sum('total');
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
        $generatedToday = Invoice::where('type', \App\Enums\InvoiceType::Recurring)
            ->whereDate('created_at', now()->today())
            ->count();

        $upcoming = \App\Models\RecurringInvoice::where('status', 'active')
            ->where('recurrence_type', '!=', \App\Enums\RecurrenceType::Manual)
            ->whereDate('next_invoice_date', '>=', now()->today())
            ->whereDate('next_invoice_date', '<=', now()->today()->addDays(7))
            ->with('customer')
            ->orderBy('next_invoice_date', 'asc')
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
            'created' => $this->getCreatedDescription($loggable),
            'updated' => $this->getUpdatedDescription($loggable),
            'deleted' => $this->getDeletedDescription($log),
            'invoice_sent' => "Invoice {$loggable?->invoice_number} sent to {$recipient}",
            'reminder_sent' => "Payment reminder sent for invoice {$loggable?->invoice_number}",
            'marked_as_paid' => "Invoice {$loggable?->invoice_number} marked as paid",
            'cancelled' => "Invoice {$loggable?->invoice_number} cancelled",
            default => ucfirst($log->action) . ' action performed',
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
