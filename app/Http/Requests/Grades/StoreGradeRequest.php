<?php

namespace App\Http\Requests\Grades;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:20000'],
            'school_id' => ['prohibited'],
            'assignment_id' => ['prohibited'],
            'submission_id' => ['prohibited'],
            'student_id' => ['prohibited'],
            'graded_by' => ['prohibited'],
            'graded_at' => ['prohibited'],
        ];
    }
}
