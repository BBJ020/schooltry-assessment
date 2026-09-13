<?php

namespace App\Actions\Assignments;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateAssignment
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** @param array<string, mixed> $attributes @param list<int> $targetStudentIds */
    public function execute(User $actor, Course $course, array $attributes, array $targetStudentIds = []): Assignment
    {
        Gate::forUser($actor)->authorize('create', Assignment::class);
        Gate::forUser($actor)->authorize('update', $course);

        return DB::transaction(function () use ($actor, $course, $attributes, $targetStudentIds): Assignment {
            $assignment = new Assignment($attributes);
            $assignment->forceFill([
                'school_id' => $actor->school_id,
                'course_id' => $course->id,
                'created_by' => $actor->id,
                'is_released' => false,
                'released_at' => null,
                'released_by' => null,
            ])->save();

            if ($assignment->target_type === 'selected') {
                $validIds = $course->students()->whereKey($targetStudentIds)->pluck('users.id')->map(fn ($id) => (int) $id)->all();
                $requestedIds = array_values(array_unique(array_map('intval', $targetStudentIds)));

                if (count($validIds) !== count($requestedIds)) {
                    throw new AuthorizationException('Every assignment target must be enrolled in this course and school.');
                }

                foreach ($validIds as $studentId) {
                    $assignment->targets()->create(['student_id' => $studentId]);
                }
            }

            $this->audit->execute($actor, 'assignment.created', $assignment, null, $assignment->getAttributes());

            return $assignment;
        });
    }
}
