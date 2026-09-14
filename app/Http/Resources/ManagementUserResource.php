<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ManagementUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'school_id' => $this->school_id,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'courses_taught' => CourseResource::collection($this->whenLoaded('taughtCourses')),
            'course_enrollments' => CourseResource::collection($this->whenLoaded('enrolledCourses')),
        ];
    }
}
