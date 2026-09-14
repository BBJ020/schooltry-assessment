<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LecturerSubmissionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_own_assignment_submission_is_visible_with_safe_display_fields_and_grade_status(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $student = $this->user($school, 'student');
        $course = $this->course($school, $lecturer);
        $assignment = $this->assignment($school, $course, $lecturer);
        $submission = $this->submission($school, $assignment, $student);
        Sanctum::actingAs($lecturer);

        $this->getJson("/api/lecturer/assignments/{$assignment->id}/submissions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $submission->id)
            ->assertJsonPath('data.0.student.name', $student->name)
            ->assertJsonPath('data.0.submission_text', 'Completed answer')
            ->assertJsonPath('data.0.file.original_filename', 'answer.pdf')
            ->assertJsonPath('data.0.grade_status', 'ungraded')
            ->assertJsonPath('data.0.submitted_at', $submission->submitted_at->toIso8601String())
            ->assertJsonMissingPath('data.0.file_path')
            ->assertJsonMissingPath('data.0.file.file_path');

        $this->grade($school, $assignment, $submission, $student, $lecturer);

        $this->getJson("/api/lecturer/assignments/{$assignment->id}/submissions")
            ->assertOk()
            ->assertJsonPath('data.0.grade_status', 'graded')
            ->assertJsonPath('data.0.grade.score', '82.00');
    }

    public function test_cross_tenant_submission_is_hidden(): void
    {
        [$school, $owner] = $this->schoolUser('lecturer');
        $student = $this->user($school, 'student');
        $course = $this->course($school, $owner);
        $assignment = $this->assignment($school, $course, $owner);
        $this->submission($school, $assignment, $student);
        [, $crossTenantLecturer] = $this->schoolUser('lecturer');
        Sanctum::actingAs($crossTenantLecturer);

        $this->getJson("/api/lecturer/assignments/{$assignment->id}/submissions")
            ->assertNotFound()
            ->assertJsonMissingPath('data.0.submission_text');
    }

    public function test_another_lecturers_submission_is_hidden(): void
    {
        [$school, $owner] = $this->schoolUser('lecturer');
        $otherLecturer = $this->user($school, 'lecturer');
        $student = $this->user($school, 'student');
        $course = $this->course($school, $owner);
        $assignment = $this->assignment($school, $course, $owner);
        $this->submission($school, $assignment, $student);
        Sanctum::actingAs($otherLecturer);

        $this->getJson("/api/lecturer/assignments/{$assignment->id}/submissions")
            ->assertNotFound()
            ->assertJsonMissingPath('data.0.submission_text');
    }

    /** @return array{School, User} */
    private function schoolUser(string $role): array
    {
        $school = School::query()->create(['name' => fake()->company(), 'slug' => fake()->unique()->slug()]);

        return [$school, $this->user($school, $role)];
    }

    private function user(School $school, string $role): User
    {
        $user = User::factory()->create(['school_id' => $school->id, 'is_active' => true]);
        $roleModel = Role::query()->firstOrCreate(['name' => $role]);
        $user->roles()->attach($roleModel->id, ['school_id' => $school->id]);

        return $user;
    }

    private function course(School $school, User $lecturer): Course
    {
        $course = new Course(['code' => fake()->unique()->bothify('???###'), 'title' => 'Secure Systems']);
        $course->forceFill(['school_id' => $school->id, 'lecturer_id' => $lecturer->id])->save();

        return $course;
    }

    private function assignment(School $school, Course $course, User $lecturer): Assignment
    {
        $assignment = new Assignment(['title' => 'Assessment', 'maximum_score' => 100, 'target_type' => 'all']);
        $assignment->forceFill(['school_id' => $school->id, 'course_id' => $course->id, 'created_by' => $lecturer->id])->save();

        return $assignment;
    }

    private function submission(School $school, Assignment $assignment, User $student): AssignmentSubmission
    {
        $submission = new AssignmentSubmission([
            'submission_text' => 'Completed answer',
            'file_path' => 'private/internal/object-key.pdf',
            'original_filename' => 'answer.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'submitted_at' => now(),
        ]);
        $submission->forceFill([
            'school_id' => $school->id,
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ])->save();

        return $submission;
    }

    private function grade(
        School $school,
        Assignment $assignment,
        AssignmentSubmission $submission,
        User $student,
        User $lecturer,
    ): Grade {
        $grade = new Grade(['score' => 82, 'feedback' => 'Good work', 'graded_at' => now()]);
        $grade->forceFill([
            'school_id' => $school->id,
            'assignment_id' => $assignment->id,
            'submission_id' => $submission->id,
            'student_id' => $student->id,
            'graded_by' => $lecturer->id,
        ])->save();

        return $grade;
    }
}
