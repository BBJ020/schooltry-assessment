<?php

namespace App\Actions\Grades;

use App\Actions\Audit\RecordAuditLog;
use App\Models\AssignmentSubmission;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GradeSubmission
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(User $grader, AssignmentSubmission $submission, float $score, ?string $feedback = null): Grade
    {
        Gate::forUser($grader)->authorize('grade', [Grade::class, $submission]);

        if ($score < 0 || $score > (float) $submission->assignment->maximum_score) {
            throw ValidationException::withMessages(['score' => 'The score must be between zero and the assignment maximum score.']);
        }

        return DB::transaction(function () use ($grader, $submission, $score, $feedback): Grade {
            $grade = Grade::query()->where('submission_id', $submission->id)->first();
            $old = $grade?->getAttributes();
            $grade ??= new Grade;
            $grade->fill(['score' => $score, 'feedback' => $feedback, 'graded_at' => now()]);
            $grade->forceFill([
                'school_id' => $grader->school_id,
                'assignment_id' => $submission->assignment_id,
                'submission_id' => $submission->id,
                'student_id' => $submission->student_id,
                'graded_by' => $grader->id,
            ])->save();
            $this->audit->execute($grader, $old ? 'grade.updated' : 'grade.created', $grade, $old, $grade->getAttributes());

            return $grade;
        });
    }
}
