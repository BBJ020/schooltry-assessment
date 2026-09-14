<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isPlatformAdministrator($user);
    }

    public function view(User $user, School $school): bool
    {
        return $this->isPlatformAdministrator($user);
    }

    public function create(User $user): bool
    {
        return $this->isPlatformAdministrator($user);
    }

    public function update(User $user, School $school): bool
    {
        return $this->isPlatformAdministrator($user);
    }

    private function isPlatformAdministrator(User $user): bool
    {
        return $user->is_active && $user->school_id === null && $user->hasRole('superadmin');
    }
}
