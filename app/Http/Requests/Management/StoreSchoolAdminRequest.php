<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'is_active' => ['sometimes', 'boolean'],
            'school_id' => ['prohibited'], 'role' => ['prohibited'], 'password' => ['prohibited'],
        ];
    }
}
