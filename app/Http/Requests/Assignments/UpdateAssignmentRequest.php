<?php

namespace App\Http\Requests\Assignments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'maximum_score' => ['sometimes', 'numeric', 'min:0.01', 'max:999999.99'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip'],
            'remove_file' => ['sometimes', 'boolean'],
            'target_type' => ['sometimes', Rule::in(['all', 'selected'])],
            'target_student_ids' => ['required_if:target_type,selected', 'array', 'min:1'],
            'target_student_ids.*' => ['integer', 'distinct'],
            'school_id' => ['prohibited'],
            'course_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'lecturer_id' => ['prohibited'],
            'is_released' => ['prohibited'],
            'released_at' => ['prohibited'],
            'released_by' => ['prohibited'],
            'file_path' => ['prohibited'],
            'file_name' => ['prohibited'],
            'file_mime_type' => ['prohibited'],
            'file_size' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->hasFile('file') && $this->boolean('remove_file')) {
                    $validator->errors()->add('remove_file', 'Remove the existing file or upload a replacement, not both.');
                }
            },
        ];
    }
}
