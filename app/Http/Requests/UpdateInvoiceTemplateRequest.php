<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'components' => ['required', 'array'],
            'components.*.key' => ['required', 'string', Rule::in($this->allowedComponentKeys())],
            'components.*.enabled' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $components = $this->input('components');
            if (!is_array($components)) {
                return;
            }

            $requiredKeys = $this->requiredComponentKeys();

            foreach ($components as $item) {
                if (isset($item['key'], $item['enabled']) && in_array($item['key'], $requiredKeys, true) && $item['enabled'] === false) {
                    $validator->errors()->add(
                        'components',
                        "The '{$item['key']}' component is required and cannot be disabled."
                    );
                }
            }
        });
    }

    private function allowedComponentKeys(): array
    {
        return array_column(config('invoice.default_components', []), 'key');
    }

    private function requiredComponentKeys(): array
    {
        return array_column(
            array_filter(config('invoice.default_components', []), fn ($c) => !empty($c['required'])),
            'key'
        );
    }
}
