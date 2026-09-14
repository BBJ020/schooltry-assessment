<?php

namespace App\Http\Resources;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin School */
class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'user_count' => $this->whenCounted('users'),
            'course_count' => $this->whenCounted('courses'),
            'admin_count' => $this->when(isset($this->admin_count), $this->admin_count),
            'lecturer_count' => $this->when(isset($this->lecturer_count), $this->lecturer_count),
            'student_count' => $this->when(isset($this->student_count), $this->student_count),
            'admins' => ManagementUserResource::collection($this->whenLoaded('admins')),
        ];
    }
}
