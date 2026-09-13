<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'student' => $this->whenLoaded('student', fn () => ['id' => $this->student->id, 'name' => $this->student->name]),
            'submission_text' => $this->submission_text,
            'file' => $this->file_path ? [
                'original_filename' => $this->original_filename,
                'mime_type' => $this->mime_type,
                'file_size' => $this->file_size,
            ] : null,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'grade' => GradeResource::make($this->whenLoaded('grade')),
        ];
    }
}
