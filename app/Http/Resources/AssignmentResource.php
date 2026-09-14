<?php

namespace App\Http\Resources;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Assignment */
class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course' => $this->whenLoaded('course', fn () => [
                'id' => $this->course->id,
                'code' => $this->course->code,
                'title' => $this->course->title,
            ]),
            'title' => $this->title,
            'description' => $this->description,
            'maximum_score' => $this->maximum_score,
            'due_at' => $this->due_at?->toIso8601String(),
            'target_type' => $this->target_type,
            'is_released' => $this->is_released,
            'released_at' => $this->released_at?->toIso8601String(),
            'has_submissions' => (bool) ($this->submissions_exists ?? false),
            'attachment' => $this->file_path ? [
                'file_name' => $this->file_name,
                'mime_type' => $this->file_mime_type,
                'file_size' => $this->file_size,
            ] : null,
            'target_student_ids' => $this->whenLoaded('targets', fn () => $this->targets->pluck('student_id')->values()),
        ];
    }
}
