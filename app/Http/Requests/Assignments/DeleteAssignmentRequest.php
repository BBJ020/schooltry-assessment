<?php

namespace App\Http\Requests\Assignments;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['prohibited'],
            'course_id' => ['prohibited'],
            'assignment_id' => ['prohibited'],
        ];
    }
}
