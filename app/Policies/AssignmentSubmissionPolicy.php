<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;

class AssignmentSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->school_id !== null;
    }

    public function view(User $user, AssignmentSubmission $submission): bool
    {
        if (! $this->sameSchool($user, $submission)) {
            return false;
        }

        if ($user->hasRole('student')) {
            return (int) $submission->student_id === (int) $user->id;
        }

        return $this->canReview($user, $submission);
    }

    public function create(User $user): bool
    {
        return $user->school_id !== null && $user->hasRole('student');
    }

    public function submit(User $user, Assignment $assignment): bool
    {
        return (int) $user->school_id === (int) $assignment->school_id
            && $user->hasRole('student')
            && $assignment->isTargetedTo($user);
    }

    public function update(User $user, AssignmentSubmission $submission): bool
    {
        return $this->sameSchool($user, $submission)
            && $user->hasRole('student')
            && (int) $submission->student_id === (int) $user->id;
    }

    public function delete(User $user, AssignmentSubmission $submission): bool
    {
        return $this->update($user, $submission);
    }

    private function sameSchool(User $user, AssignmentSubmission $submission): bool
    {
        return (int) $user->school_id === (int) $submission->school_id;
    }

    private function canReview(User $user, AssignmentSubmission $submission): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('lecturer') && (int) $submission->assignment?->course?->lecturer_id === (int) $user->id);
    }
}
