<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use BelongsToSchool;

    protected $fillable = ['code', 'title', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected function tenantParentKeys(): array
    {
        return ['lecturer_id' => User::class];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function students(): BelongsToMany
    {
        $relation = $this->belongsToMany(User::class, 'course_student', 'course_id', 'student_id')
            ->using(CourseStudent::class)
            ->withPivot(['school_id', 'enrolled_at']);

        $schoolId = $this->school_id ?? app(TenantContext::class)->schoolId();

        return $schoolId === null ? $relation : $relation->withPivotValue('school_id', $schoolId);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }
        if ($user->hasRole('lecturer')) {
            return $query->where('lecturer_id', $user->id);
        }

        return $query->whereHas('students', fn (Builder $students) => $students->whereKey($user->id));
    }
}
