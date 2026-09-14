<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;

class AssignSchoolAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['user_id' => ['required', 'integer', 'exists:users,id'], 'school_id' => ['prohibited'], 'role' => ['prohibited']];
    }
}
