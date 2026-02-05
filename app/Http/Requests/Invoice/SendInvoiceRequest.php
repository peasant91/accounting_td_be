<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class SendInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_email' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
