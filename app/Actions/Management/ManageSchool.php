<?php

namespace App\Actions\Management;

use App\Actions\Audit\RecordAuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ManageSchool
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): School
    {
        Gate::forUser($actor)->authorize('create', School::class);

        return DB::transaction(function () use ($actor, $attributes): School {
            $school = School::query()->create($attributes);
            $this->audit->execute($actor, 'school.created', $school, null, $this->snapshot($school), (int) $school->id);

            return $school;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, School $school, array $attributes): School
    {
        Gate::forUser($actor)->authorize('update', $school);

        return DB::transaction(function () use ($actor, $school, $attributes): School {
            $old = $this->snapshot($school);
            $school->update($attributes);
            $this->audit->execute($actor, 'school.updated', $school, $old, $this->snapshot($school), (int) $school->id);

            return $school->refresh();
        });
    }

    public function setActive(User $actor, School $school, bool $active): School
    {
        Gate::forUser($actor)->authorize('update', $school);

        return DB::transaction(function () use ($actor, $school, $active): School {
            $old = ['is_active' => (bool) $school->is_active];
            $school->update(['is_active' => $active]);
            $this->audit->execute($actor, $active ? 'school.activated' : 'school.deactivated', $school, $old, ['is_active' => $active], (int) $school->id);

            return $school->refresh();
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(School $school): array
    {
        return $school->only(['name', 'slug', 'email', 'phone', 'is_active']);
    }
}
