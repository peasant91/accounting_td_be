<?php

namespace App\Http\Requests\CurrencyRate;

use Illuminate\Foundation\Http\FormRequest;

class UpsertRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rate_to_base' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
