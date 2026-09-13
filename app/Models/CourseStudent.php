<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CourseStudent extends Pivot
{
    use BelongsToSchool;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'course_student';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['enrolled_at' => 'datetime'];
    }

    protected function tenantParentKeys(): array
    {
        return ['course_id' => Course::class, 'student_id' => User::class];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
