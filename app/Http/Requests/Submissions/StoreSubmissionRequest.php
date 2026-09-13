<?php

namespace App\Http\Requests\Submissions;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_text' => ['nullable', 'string', 'max:20000', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,txt,png,jpg,jpeg', 'required_without:submission_text'],
            'file_path' => ['prohibited'],
            'original_filename' => ['prohibited'],
            'mime_type' => ['prohibited'],
            'file_size' => ['prohibited'],
            'school_id' => ['prohibited'],
            'assignment_id' => ['prohibited'],
            'student_id' => ['prohibited'],
            'graded_by' => ['prohibited'],
            'released_by' => ['prohibited'],
        ];
    }
}
