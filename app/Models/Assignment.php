<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $due_at
 * @property Carbon|null $released_at
 */
class Assignment extends Model
{
    use BelongsToSchool;

    protected $fillable = ['title', 'description', 'maximum_score', 'due_at', 'target_type', 'is_released', 'released_at'];

    protected function casts(): array
    {
        return ['maximum_score' => 'decimal:2', 'due_at' => 'datetime', 'is_released' => 'boolean', 'released_at' => 'datetime'];
    }

    protected function tenantParentKeys(): array
    {
        return ['course_id' => Course::class, 'created_by' => User::class, 'released_by' => User::class];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AssignmentTarget::class);
    }

    public function targetedStudents(): BelongsToMany
    {
        $relation = $this->belongsToMany(User::class, 'assignment_targets', 'assignment_id', 'student_id')
            ->withTimestamps()
            ->withPivot('school_id');

        $schoolId = $this->school_id ?? app(TenantContext::class)->schoolId();

        return $schoolId === null ? $relation : $relation->withPivotValue('school_id', $schoolId);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function isTargetedTo(User $user): bool
    {
        if ((int) $user->school_id !== (int) $this->school_id) {
            return false;
        }

        if ($this->target_type === 'selected') {
            return $this->targets()->where('student_id', $user->id)->exists();
        }

        return $this->course->students()->whereKey($user->id)->exists();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }
        if ($user->hasRole('lecturer')) {
            return $query->whereHas('course', fn (Builder $course) => $course->where('lecturer_id', $user->id));
        }

        return $query->where(function (Builder $assignments) use ($user): void {
            $assignments->where(function (Builder $all) use ($user): void {
                $all->where('target_type', 'all')
                    ->whereHas('course.students', fn (Builder $students) => $students->whereKey($user->id));
            })->orWhere(function (Builder $selected) use ($user): void {
                $selected->where('target_type', 'selected')
                    ->whereHas('targets', fn (Builder $targets) => $targets->where('student_id', $user->id));
            });
        });
    }
}
