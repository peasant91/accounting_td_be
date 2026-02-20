<?php

namespace App\Models;

use App\Enums\RecurrenceType;
use App\Enums\RecurrenceUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'title',
        'recurrence_type',
        'recurrence_interval',
        'recurrence_unit',
        'total_count',
        'generated_count',
        'start_date',
        'next_invoice_date',
        'status',
        'line_items',
        'tax_rate',
        'currency',
        'due_date_offset',
        'notes',
        'last_generated_at',
    ];

    protected $casts = [
        'line_items' => 'array',
        'start_date' => 'date',
        'next_invoice_date' => 'date',
        'last_generated_at' => 'datetime',
        'recurrence_type' => RecurrenceType::class,
        'recurrence_interval' => 'integer',
        'recurrence_unit' => RecurrenceUnit::class,
        'total_count' => 'integer',
        'generated_count' => 'integer',
        'tax_rate' => 'decimal:2',
        'due_date_offset' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
