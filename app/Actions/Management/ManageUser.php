<?php

namespace App\Actions\Management;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageUser
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, School $school, array $attributes, string $roleName): array
    {
        Gate::forUser($actor)->authorize('create', User::class);
        $this->assertTenantRole($roleName);
        $temporaryPassword = Str::password(20);

        $user = DB::transaction(function () use ($actor, $school, $attributes, $roleName, $temporaryPassword): User {
            $user = new User($attributes);
            $user->forceFill(['school_id' => $school->id, 'password' => $temporaryPassword])->save();
            $this->replaceRole($user, $roleName, (int) $school->id);
            $action = $actor->hasRole('superadmin') ? 'school_admin.created' : 'user.created';
            $this->audit->execute($actor, $action, $user, null, $this->snapshot($user, $roleName), (int) $school->id);

            return $user;
        });

        return ['user' => $user->load('roles:id,name'), 'temporary_password' => $temporaryPassword];
    }

    public function assignSchoolAdmin(User $actor, School $school, User $user): User
    {
        Gate::forUser($actor)->authorize('update', $school);

        if ($user->hasRole('superadmin') || ($user->school_id !== null && (int) $user->school_id !== (int) $school->id)) {
            throw ValidationException::withMessages(['user_id' => 'The user is not eligible for assignment to this school.']);
        }

        return DB::transaction(function () use ($actor, $school, $user): User {
            $old = ['school_id' => $user->school_id, 'roles' => $user->roles()->pluck('name')->all()];
            $user->forceFill(['school_id' => $school->id])->save();
            $this->replaceRole($user, 'admin', (int) $school->id);
            $this->audit->execute($actor, 'school_admin.assigned', $user, $old, $this->snapshot($user, 'admin'), (int) $school->id);

            return $user->load('roles:id,name');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, User $user, array $attributes): User
    {
        Gate::forUser($actor)->authorize('update', $user);

        return DB::transaction(function () use ($actor, $user, $attributes): User {
            $old = $user->only(['name', 'email']);
            $user->update($attributes);
            $this->audit->execute($actor, 'user.updated', $user, $old, $user->only(['name', 'email']), (int) $user->school_id);

            return $user->refresh()->load('roles:id,name');
        });
    }

    public function changeRole(User $actor, User $user, string $roleName): User
    {
        Gate::forUser($actor)->authorize('update', $user);
        $this->assertTenantRole($roleName);

        return DB::transaction(function () use ($actor, $user, $roleName): User {
            $old = ['roles' => $user->roles()->pluck('name')->all()];
            $this->replaceRole($user, $roleName, (int) $user->school_id);
            $this->audit->execute($actor, 'user.role_changed', $user, $old, ['roles' => [$roleName]], (int) $user->school_id);

            return $user->load('roles:id,name');
        });
    }

    public function removeAdmin(User $actor, School $school, User $user): User
    {
        Gate::forUser($actor)->authorize('update', $school);
        if ((int) $user->school_id !== (int) $school->id || ! $user->hasRole('admin')) {
            throw ValidationException::withMessages(['user' => 'The school administrator is unavailable.']);
        }

        return DB::transaction(function () use ($actor, $school, $user): User {
            DB::table('role_user')->where('user_id', $user->id)->where('school_id', $school->id)->delete();
            $this->audit->execute($actor, 'school_admin.role_removed', $user, ['roles' => ['admin']], ['roles' => []], (int) $school->id);

            return $user->load('roles:id,name');
        });
    }

    public function setActive(User $actor, User $user, bool $active): User
    {
        Gate::forUser($actor)->authorize('update', $user);

        return DB::transaction(function () use ($actor, $user, $active): User {
            $old = ['is_active' => (bool) $user->is_active];
            $user->forceFill(['is_active' => $active])->save();
            $this->audit->execute($actor, $active ? 'user.activated' : 'user.deactivated', $user, $old, ['is_active' => $active], (int) $user->school_id);

            return $user->refresh()->load('roles:id,name');
        });
    }

    /** @return array{user: User, temporary_password: string} */
    public function resetPassword(User $actor, User $user): array
    {
        Gate::forUser($actor)->authorize('resetPassword', $user);
        $temporaryPassword = Str::password(20);
        DB::transaction(function () use ($actor, $user, $temporaryPassword): void {
            $user->forceFill(['password' => $temporaryPassword])->save();
            $this->audit->execute($actor, 'user.password_reset', $user, null, ['password_reset' => true], (int) $user->school_id);
        });

        return ['user' => $user, 'temporary_password' => $temporaryPassword];
    }

    private function replaceRole(User $user, string $roleName, int $schoolId): void
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        DB::table('role_user')->where('user_id', $user->id)->delete();
        DB::table('role_user')->insert(['school_id' => $schoolId, 'role_id' => $role->id, 'user_id' => $user->id]);
    }

    private function assertTenantRole(string $roleName): void
    {
        if (! in_array($roleName, ['admin', 'lecturer', 'student'], true)) {
            throw ValidationException::withMessages(['role' => 'The selected tenant role is invalid.']);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user, string $roleName): array
    {
        return ['name' => $user->name, 'email' => $user->email, 'is_active' => (bool) $user->is_active, 'role' => $roleName];
    }
}
