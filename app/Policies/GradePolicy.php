<?php

namespace App\Policies;

use App\Models\AssignmentSubmission;
use App\Models\Grade;
use App\Models\User;

class GradePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->school_id !== null;
    }

    public function view(User $user, Grade $grade): bool
    {
        if (! $this->sameSchool($user, $grade) || ! $grade->assignment()->where('is_released', true)->exists()) {
            return false;
        }
        if ($user->hasRole('student')) {
            return (int) $grade->student_id === (int) $user->id;
        }

        return $this->canManage($user, $grade);
    }

    public function create(User $user): bool
    {
        return $user->school_id !== null && ($user->hasRole('admin') || $user->hasRole('lecturer'));
    }

    public function grade(User $user, AssignmentSubmission $submission): bool
    {
        if ((int) $user->school_id !== (int) $submission->school_id) {
            return false;
        }

        return $user->hasRole('admin')
            || ($user->hasRole('lecturer') && (int) $submission->assignment?->course?->lecturer_id === (int) $user->id);
    }

    public function update(User $user, Grade $grade): bool
    {
        return $this->sameSchool($user, $grade) && $this->canManage($user, $grade);
    }

    public function delete(User $user, Grade $grade): bool
    {
        return $this->update($user, $grade);
    }

    private function sameSchool(User $user, Grade $grade): bool
    {
        return (int) $user->school_id === (int) $grade->school_id;
    }

    private function canManage(User $user, Grade $grade): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('lecturer') && (int) $grade->assignment?->course?->lecturer_id === (int) $user->id);
    }
}
