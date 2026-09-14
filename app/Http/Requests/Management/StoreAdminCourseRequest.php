<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('courses', 'code')->where('school_id', $this->user()->school_id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'lecturer_id' => ['required', 'integer'],
            'school_id' => ['prohibited'],
        ];
    }
}
