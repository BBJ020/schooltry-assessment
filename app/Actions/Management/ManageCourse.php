<?php

namespace App\Actions\Management;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Courses\CreateCourse;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageCourse
{
    public function __construct(private readonly CreateCourse $createCourse, private readonly RecordAuditLog $audit) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, User $lecturer, array $attributes): Course
    {
        return $this->createCourse->execute($actor, $attributes, $lecturer);
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Course $course, User $lecturer, array $attributes): Course
    {
        Gate::forUser($actor)->authorize('update', $course);
        if ((int) $lecturer->school_id !== (int) $actor->school_id || ! $lecturer->hasRole('lecturer')) {
            throw ValidationException::withMessages(['lecturer_id' => 'Select a lecturer from this school.']);
        }

        return DB::transaction(function () use ($actor, $course, $lecturer, $attributes): Course {
            $old = $course->only(['code', 'title', 'description', 'is_active', 'lecturer_id']);
            $course->fill($attributes)->forceFill(['lecturer_id' => $lecturer->id])->save();
            $this->audit->execute($actor, 'course.updated', $course, $old, $course->only(['code', 'title', 'description', 'is_active', 'lecturer_id']));

            return $course->refresh()->load('lecturer:id,name');
        });
    }

    /** @param list<int> $studentIds */
    public function enroll(User $actor, Course $course, array $studentIds): void
    {
        Gate::forUser($actor)->authorize('update', $course);
        $students = User::query()->whereKey($studentIds)->whereHas('roles', fn ($query) => $query->where('roles.name', 'student'))->get();
        if ($students->count() !== count(array_unique($studentIds))) {
            throw ValidationException::withMessages(['student_ids' => 'Every student must belong to this school and have the student role.']);
        }

        $duplicates = DB::table('course_student')->where('course_id', $course->id)->whereIn('student_id', $studentIds)->exists();
        if ($duplicates) {
            throw ValidationException::withMessages(['student_ids' => 'One or more students are already enrolled.']);
        }

        DB::transaction(function () use ($actor, $course, $studentIds): void {
            $rows = array_map(fn (int $studentId): array => [
                'school_id' => $actor->school_id,
                'course_id' => $course->id,
                'student_id' => $studentId,
                'enrolled_at' => now(),
            ], $studentIds);
            DB::table('course_student')->insert($rows);
            $this->audit->execute($actor, 'course.students_enrolled', $course, null, ['student_ids' => $studentIds]);
        });
    }

    public function unenroll(User $actor, Course $course, User $student): void
    {
        Gate::forUser($actor)->authorize('update', $course);
        if ((int) $student->school_id !== (int) $actor->school_id || ! $student->hasRole('student')) {
            throw ValidationException::withMessages(['student' => 'The enrollment is unavailable.']);
        }

        DB::transaction(function () use ($actor, $course, $student): void {
            $deleted = DB::table('course_student')
                ->where('school_id', $actor->school_id)->where('course_id', $course->id)->where('student_id', $student->id)->delete();
            if ($deleted === 0) {
                throw ValidationException::withMessages(['student' => 'The enrollment is unavailable.']);
            }
            $this->audit->execute($actor, 'course.student_unenrolled', $course, ['student_id' => $student->id], null);
        });
    }
}
