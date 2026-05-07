<?php

namespace App\Http\Requests\RecurringInvoice;

use App\Enums\RecurrenceType;
use App\Enums\RecurrenceUnit;
use App\Enums\RecurringStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'recurrence_type' => ['sometimes', Rule::enum(RecurrenceType::class)],
            'recurrence_interval' => ['sometimes', 'integer', 'min:1'],
            'recurrence_unit' => ['nullable', Rule::enum(RecurrenceUnit::class)],
            'total_count' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['sometimes', 'date'],
            'line_items' => ['sometimes', 'array', 'min:1'],
            'line_items.*.description' => ['required_with:line_items', 'string', 'max:200'],
            'line_items.*.notes' => ['nullable', 'string', 'max:2000'],
            'line_items.*.quantity' => ['required_with:line_items', 'numeric', 'gt:0'],
            'line_items.*.unit_price' => ['required_with:line_items', 'numeric', 'min:0'],
            'tax_rate' => ['sometimes', 'numeric'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'due_date_offset' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(RecurringStatus::class)],
        ];
    }
}
