<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class MarkAsPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'in:cash,bank_transfer,credit_card,other'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
