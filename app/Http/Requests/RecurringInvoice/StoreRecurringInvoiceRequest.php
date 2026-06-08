<?php

namespace App\Http\Requests\RecurringInvoice;

use App\Enums\RecurrenceType;
use App\Enums\RecurrenceUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'recurrence_type' => ['required', Rule::enum(RecurrenceType::class)],
            'recurrence_interval' => ['required', 'integer', 'min:1'],
            'recurrence_unit' => ['nullable', Rule::enum(RecurrenceUnit::class)],
            'total_count' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:200'],
            'line_items.*.notes' => ['nullable', 'string', 'max:2000'],
            'line_items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric'],
            'currency' => ['required', 'string', 'size:3'],
            'due_date_offset' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'use_unique_code' => ['nullable', 'boolean'],
        ];
    }
}
