<?php

namespace App\Http\Resources;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Course */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'lecturer' => $this->whenLoaded('lecturer', fn () => ['id' => $this->lecturer->id, 'name' => $this->lecturer->name]),
        ];
    }
}
