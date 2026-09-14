<?php

namespace App\Policies;

use App\Models\User;

class ManagedUserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isSuperadmin($actor) || $this->isSchoolAdmin($actor);
    }

    public function view(User $actor, User $user): bool
    {
        return ($this->isSuperadmin($actor) && ! $user->hasRole('superadmin'))
            || ($this->isSchoolAdmin($actor) && $this->sameSchool($actor, $user) && ! $user->hasRole('superadmin'));
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, User $user): bool
    {
        return $this->view($actor, $user) && (int) $actor->id !== (int) $user->id;
    }

    public function resetPassword(User $actor, User $user): bool
    {
        return $this->update($actor, $user);
    }

    private function isSuperadmin(User $user): bool
    {
        return $user->is_active && $user->school_id === null && $user->hasRole('superadmin');
    }

    private function isSchoolAdmin(User $user): bool
    {
        return $user->is_active && $user->school_id !== null && $user->hasRole('admin');
    }

    private function sameSchool(User $actor, User $user): bool
    {
        return (int) $actor->school_id === (int) $user->school_id;
    }
}
