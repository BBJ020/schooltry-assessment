<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon|null $graded_at */
class Grade extends Model
{
    use BelongsToSchool;

    protected $fillable = ['score', 'feedback', 'graded_at'];

    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'graded_at' => 'datetime'];
    }

    protected function tenantParentKeys(): array
    {
        return ['assignment_id' => Assignment::class, 'submission_id' => AssignmentSubmission::class, 'student_id' => User::class, 'graded_by' => User::class];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'submission_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->whereHas('assignment', fn (Builder $assignment) => $assignment->where('is_released', true));

        if ($user->hasRole('admin')) {
            return $query;
        }
        if ($user->hasRole('lecturer')) {
            return $query->whereHas('assignment.course', fn (Builder $course) => $course->where('lecturer_id', $user->id));
        }

        return $query->where('student_id', $user->id);
    }
}
