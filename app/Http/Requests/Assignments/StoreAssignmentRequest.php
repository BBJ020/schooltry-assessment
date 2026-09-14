<?php

namespace App\Http\Requests\Assignments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'maximum_score' => ['sometimes', 'numeric', 'min:0.01', 'max:999999.99'],
            'due_at' => ['nullable', 'date'],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip'],
            'target_type' => ['required', Rule::in(['all', 'selected'])],
            'target_student_ids' => ['required_if:target_type,selected', 'array', 'min:1'],
            'target_student_ids.*' => ['integer', 'distinct'],
            'school_id' => ['prohibited'],
            'course_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'is_released' => ['prohibited'],
            'released_at' => ['prohibited'],
            'released_by' => ['prohibited'],
            'file_path' => ['prohibited'],
            'file_name' => ['prohibited'],
            'file_mime_type' => ['prohibited'],
            'file_size' => ['prohibited'],
            'lecturer_id' => ['prohibited'],
        ];
    }
}
