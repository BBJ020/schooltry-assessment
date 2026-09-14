<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function view(User $user, Course $course): bool
    {
        return $this->sameSchool($user, $course)
            && ($this->canManage($user, $course) || $course->students()->whereKey($user->id)->exists());
    }

    public function create(User $user): bool
    {
        return $this->hasTenant($user) && $user->hasRole('admin');
    }

    public function update(User $user, Course $course): bool
    {
        return $this->sameSchool($user, $course) && $this->canManage($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }

    private function hasTenant(User $user): bool
    {
        return $user->is_active && $user->school_id !== null;
    }

    private function sameSchool(User $user, Course $course): bool
    {
        return (int) $user->school_id === (int) $course->school_id;
    }

    private function canManage(User $user, Course $course): bool
    {
        return $user->hasRole('admin') || ($user->hasRole('lecturer') && (int) $course->lecturer_id === (int) $user->id);
    }
}
