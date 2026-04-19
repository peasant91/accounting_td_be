<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class UpdateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;  // gated by route middleware role:super_admin
    }

    public function rules(): array
    {
        $id = $this->route('admin')?->id ?? $this->route('admin');
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['sometimes', 'nullable', Password::min(12)->letters()->numbers()],
            'role' => ['sometimes', new Enum(UserRole::class)],
        ];
    }
}
