<?php

namespace App\Support\Tenancy;

use App\Models\User;

final class TenantContext
{
    private ?int $schoolId = null;

    public function setFor(User $user): void
    {
        $this->schoolId = $user->school_id === null ? null : (int) $user->school_id;
    }

    public function schoolId(): ?int
    {
        return $this->schoolId;
    }

    public function clear(): void
    {
        $this->schoolId = null;
    }
}
