<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;

class EnrollStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['student_ids' => ['required', 'array', 'min:1', 'max:100'], 'student_ids.*' => ['required', 'integer', 'distinct'], 'school_id' => ['prohibited']];
    }
}
