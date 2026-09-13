<?php

namespace App\Actions\Courses;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateCourse
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, array $attributes, ?User $lecturer = null): Course
    {
        Gate::forUser($actor)->authorize('create', Course::class);
        $lecturer ??= $actor;

        if ((int) $lecturer->school_id !== (int) $actor->school_id || ! $lecturer->hasRole('lecturer')) {
            throw new AuthorizationException('The lecturer must belong to this school and have the lecturer role.');
        }

        if ($actor->hasRole('lecturer') && (int) $lecturer->id !== (int) $actor->id) {
            throw new AuthorizationException('Lecturers may only create courses they own.');
        }

        return DB::transaction(function () use ($actor, $attributes, $lecturer): Course {
            $course = new Course($attributes);
            $course->forceFill(['school_id' => $actor->school_id, 'lecturer_id' => $lecturer->id])->save();
            $this->audit->execute($actor, 'course.created', $course, null, $course->getAttributes());

            return $course;
        });
    }
}
