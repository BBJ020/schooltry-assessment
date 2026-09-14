<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int|null $admin_count
 * @property-read int|null $lecturer_count
 * @property-read int|null $student_count
 */
class School extends Model
{
    protected $fillable = ['name', 'slug', 'email', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function admins(): HasMany
    {
        return $this->users()->whereHas('roles', fn ($query) => $query->where('roles.name', 'admin'));
    }

    public function scopeWithRoleCounts(Builder $query): Builder
    {
        return $query->withCount([
            'users as admin_count' => fn (Builder $users) => $users->whereHas('roles', fn (Builder $roles) => $roles->where('roles.name', 'admin')),
            'users as lecturer_count' => fn (Builder $users) => $users->whereHas('roles', fn (Builder $roles) => $roles->where('roles.name', 'lecturer')),
            'users as student_count' => fn (Builder $users) => $users->whereHas('roles', fn (Builder $roles) => $roles->where('roles.name', 'student')),
        ]);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function assignmentTargets(): HasMany
    {
        return $this->hasMany(AssignmentTarget::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
