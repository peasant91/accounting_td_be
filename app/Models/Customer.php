<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Services\Audit\AuditsChanges;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, HasActivityLog, AuditsChanges;

    protected $fillable = [
        'name',
        'company_name',
        'email',
        'phone',
        'currency',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'tax_id',
        'notes',
        'status',
    ];

    protected $casts = [
        'status' => CustomerStatus::class,
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getTotalReceivableAttribute(): float
    {
        if (array_key_exists('total_receivable', $this->attributes)) {
            return (float) $this->attributes['total_receivable'];
        }

        return (float) $this->invoices()->unpaid()->sum('total');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }
    /**
     * Get the invoice template for the customer.
     */
    public function invoiceTemplate(): HasOne
    {
        return $this->hasOne(InvoiceTemplate::class);
    }
    /**
     * Get the recurring invoices for the customer.
     */
    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class);
    }
}
