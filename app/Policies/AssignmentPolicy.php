<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->school_id !== null;
    }

    public function view(User $user, Assignment $assignment): bool
    {
        if (! $this->sameSchool($user, $assignment)) {
            return false;
        }
        if ($this->canManage($user, $assignment)) {
            return true;
        }

        return $user->hasRole('student') && $assignment->isTargetedTo($user);
    }

    public function create(User $user): bool
    {
        return $user->school_id !== null && ($user->hasRole('admin') || $user->hasRole('lecturer'));
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $this->sameSchool($user, $assignment)
            && $this->canManage($user, $assignment)
            && ! $assignment->submissions()->exists();
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    public function release(User $user, Assignment $assignment): bool
    {
        return $this->sameSchool($user, $assignment) && $this->canManage($user, $assignment);
    }

    public function viewSubmissions(User $user, Assignment $assignment): bool
    {
        return $this->sameSchool($user, $assignment) && $this->canManage($user, $assignment);
    }

    private function sameSchool(User $user, Assignment $assignment): bool
    {
        return (int) $user->school_id === (int) $assignment->school_id;
    }

    private function canManage(User $user, Assignment $assignment): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('lecturer') && (int) $assignment->course?->lecturer_id === (int) $user->id);
    }
}
