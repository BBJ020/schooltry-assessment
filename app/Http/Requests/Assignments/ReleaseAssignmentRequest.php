<?php

namespace App\Http\Requests\Assignments;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['prohibited'],
            'is_released' => ['prohibited'],
            'released_at' => ['prohibited'],
            'released_by' => ['prohibited'],
        ];
    }
}
