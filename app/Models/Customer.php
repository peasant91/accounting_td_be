<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes, HasActivityLog;

    protected $fillable = [
        'name',
        'email',
        'phone',
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

    /**
     * Get the invoices for the customer.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the total receivable amount (sum of unpaid invoices).
     */
    public function getTotalReceivableAttribute(): float
    {
        return (float) $this->invoices()
            ->whereIn('status', ['sent', 'overdue'])
            ->sum('total');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to search by name, email, or phone.
     */
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
}
