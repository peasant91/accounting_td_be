<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\InvoiceType;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes, HasActivityLog;

    protected $fillable = [
        'customer_id',
        'recurring_invoice_id',
        'type',
        'currency',
        'invoice_number',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'status',
        'notes',
        'internal_notes',
        'cancellation_reason',
        'payment_date',
        'payment_method',
        'payment_reference',
        'payment_notes',
        'payment_proof_path',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'payment_date' => 'date',
        'status' => InvoiceStatus::class,
        'type' => InvoiceType::class,
        'payment_method' => PaymentMethod::class,
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Get the customer that owns the invoice.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the recurring invoice that generated this invoice.
     */
    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    /**
     * Get the invoice items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * Calculate and update totals based on items.
     */
    public function calculateTotals(): void
    {
        $subtotal = $this->items()->sum('amount');
        $taxAmount = $subtotal * ($this->tax_rate / 100);
        $total = $subtotal + $taxAmount;

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    /**
     * Check if the invoice can be edited.
     */
    public function isEditable(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    /**
     * Check if the invoice can be deleted.
     */
    public function isDeletable(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    /**
     * Get available actions based on status.
     */
    public function getAvailableActionsAttribute(): array
    {
        return match ($this->status) {
            InvoiceStatus::Draft => ['edit', 'send', 'delete'],
            InvoiceStatus::Sent => ['mark_as_paid', 'resend', 'cancel'],
            InvoiceStatus::Overdue => ['send_reminder', 'mark_as_paid', 'cancel'],
            InvoiceStatus::Paid => [],
            InvoiceStatus::Cancelled => [],
        };
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by customer.
     */
    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to search by invoice number or customer name.
     */
    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('customer', function ($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%");
                });
        });
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, ?string $from, ?string $to, string $field = 'invoice_date')
    {
        if ($from) {
            $query->where($field, '>=', $from);
        }
        if ($to) {
            $query->where($field, '<=', $to);
        }
        return $query;
    }
}
