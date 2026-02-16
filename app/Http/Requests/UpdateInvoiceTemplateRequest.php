<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $defaultComponents = config('invoice.default_components', []);
        $allowedKeys = array_column($defaultComponents, 'key');

        $requiredKeys = [];
        foreach ($defaultComponents as $comp) {
            if ($comp['required']) {
                $requiredKeys[] = $comp['key'];
            }
        }

        return [
            'components' => ['required', 'array'],
            'components.*.key' => ['required', 'string', Rule::in($allowedKeys)],
            'components.*.enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $components = $this->input('components');
            if (!is_array($components))
                return;

            $defaultComponents = config('invoice.default_components', []);
            $requiredKeys = [];
            foreach ($defaultComponents as $comp) {
                if ($comp['required']) {
                    $requiredKeys[] = $comp['key'];
                }
            }

            foreach ($components as $item) {
                if (isset($item['key'], $item['enabled']) && in_array($item['key'], $requiredKeys) && $item['enabled'] === false) {
                    $validator->errors()->add(
                        'components',
                        "The '{$item['key']}' component is required and cannot be disabled."
                    );
                }
            }
        });
    }
}
