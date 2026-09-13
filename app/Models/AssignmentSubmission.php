<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssignmentSubmission extends Model
{
    use BelongsToSchool;

    protected $fillable = ['submission_text', 'file_path', 'original_filename', 'mime_type', 'file_size', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'file_size' => 'integer'];
    }

    protected function tenantParentKeys(): array
    {
        return ['assignment_id' => Assignment::class, 'student_id' => User::class];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function grade(): HasOne
    {
        return $this->hasOne(Grade::class, 'submission_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }
        if ($user->hasRole('lecturer')) {
            return $query->whereHas('assignment.course', fn (Builder $course) => $course->where('lecturer_id', $user->id));
        }

        return $query->where('student_id', $user->id);
    }
}
