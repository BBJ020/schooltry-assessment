<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('courses', 'code')->where('school_id', $this->user()->school_id)->ignore($this->route('course'))],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'lecturer_id' => ['required', 'integer'],
            'school_id' => ['prohibited'],
        ];
    }
}
