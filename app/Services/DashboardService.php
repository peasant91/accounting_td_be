<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Get complete dashboard summary.
     */
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

    /**
     * Get total receivables (sum of unpaid invoices).
     */
    public function getTotalReceivables(): float
    {
        return (float) Invoice::whereIn('status', [
            InvoiceStatus::Sent,
            InvoiceStatus::Overdue,
        ])->sum('total');
    }

    /**
     * Get total active customers count.
     */
    public function getTotalCustomers(): int
    {
        return Customer::where('status', 'active')->count();
    }

    /**
     * Get invoices due this month.
     */
    public function getInvoicesDueThisMonth(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $invoices = Invoice::whereIn('status', [
            InvoiceStatus::Sent,
            InvoiceStatus::Overdue,
        ])
            ->whereBetween('due_date', [$startOfMonth, $endOfMonth])
            ->get();

        return [
            'count' => $invoices->count(),
            'amount' => (float) $invoices->sum('total'),
        ];
    }

    /**
     * Get recurring invoices summary.
     */
    public function getRecurringInvoicesSummary(): array
    {
        // Generated today
        $generatedToday = Invoice::where('type', \App\Enums\InvoiceType::Recurring)
            ->whereDate('created_at', now()->today())
            ->count();

        // Upcoming in next 7 days
        $upcoming = \App\Models\RecurringInvoice::where('status', 'active')
            ->where('recurrence_type', '!=', \App\Enums\RecurrenceType::Manual)
            ->whereDate('next_invoice_date', '>=', now()->today())
            ->whereDate('next_invoice_date', '<=', now()->today()->addDays(7))
            ->with('customer')
            ->orderBy('next_invoice_date', 'asc')
            ->get()
            ->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'customer_id' => $inv->customer_id,
                    'customer_name' => $inv->customer->name,
                    'title' => $inv->title,
                    'next_invoice_date' => $inv->next_invoice_date?->toDateString(),
                ];
            });

        return [
            'generated_today' => $generatedToday,
            'upcoming' => $upcoming,
        ];
    }

    /**
     * Get recent activity.
     */
    public function getRecentActivity(int $limit = 5): Collection
    {
        return ActivityLog::with('loggable')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $this->formatActivityDescription($log),
                    'created_at' => $log->created_at?->toISOString(),
                ];
            });
    }

    /**
     * Format activity description based on action type.
     */
    private function formatActivityDescription(ActivityLog $log): string
    {
        $loggable = $log->loggable;
        $recipient = $log->properties['recipient'] ?? 'customer';

        return match ($log->action) {
            'created' => $this->getCreatedDescription($log, $loggable),
            'updated' => $this->getUpdatedDescription($log, $loggable),
            'deleted' => $this->getDeletedDescription($log, $loggable),
            'invoice_sent' => "Invoice {$loggable?->invoice_number} sent to {$recipient}",
            'reminder_sent' => "Payment reminder sent for invoice {$loggable?->invoice_number}",
            'marked_as_paid' => "Invoice {$loggable?->invoice_number} marked as paid",
            'cancelled' => "Invoice {$loggable?->invoice_number} cancelled",
            default => ucfirst($log->action) . ' action performed',
        };
    }

    private function getCreatedDescription(ActivityLog $log, $loggable): string
    {
        if ($loggable instanceof Customer) {
            return "Customer {$loggable->name} created";
        }
        if ($loggable instanceof Invoice) {
            return "Invoice {$loggable->invoice_number} created";
        }
        return 'New record created';
    }

    private function getUpdatedDescription(ActivityLog $log, $loggable): string
    {
        if ($loggable instanceof Customer) {
            return "Customer {$loggable->name} updated";
        }
        if ($loggable instanceof Invoice) {
            return "Invoice {$loggable->invoice_number} updated";
        }
        return 'Record updated';
    }

    private function getDeletedDescription(ActivityLog $log, $loggable): string
    {
        $type = class_basename($log->loggable_type);
        return "{$type} deleted";
    }
}
