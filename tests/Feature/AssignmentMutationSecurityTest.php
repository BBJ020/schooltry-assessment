<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentMutationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecturer_can_edit_audience_and_delete_own_assignment_without_submissions_with_auditing(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $student = $this->user($school, 'student');
        $course = $this->course($school, $lecturer);
        $course->students()->attach($student->id, ['school_id' => $school->id]);
        $assignment = $this->assignment($school, $course, $lecturer);
        Sanctum::actingAs($lecturer);

        $this->putJson("/api/lecturer/assignments/{$assignment->id}", [
            'title' => 'Updated assessment',
            'description' => 'Updated instructions',
            'maximum_score' => 75,
            'due_at' => now()->addWeek()->toIso8601String(),
            'target_type' => 'selected',
            'target_student_ids' => [$student->id],
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated assessment')
            ->assertJsonPath('data.target_type', 'selected')
            ->assertJsonPath('data.target_student_ids.0', $student->id)
            ->assertJsonMissingPath('data.file_path');

        $this->assertDatabaseHas('assignment_targets', [
            'school_id' => $school->id,
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'action' => 'assignment.updated',
            'auditable_id' => $assignment->id,
        ]);

        $this->deleteJson("/api/lecturer/assignments/{$assignment->id}")->assertNoContent();

        $this->assertDatabaseMissing('assignments', ['id' => $assignment->id]);
        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'action' => 'assignment.deleted',
            'auditable_id' => $assignment->id,
        ]);
    }

    public function test_edit_and_delete_are_blocked_after_any_submission_exists(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $student = $this->user($school, 'student');
        $course = $this->course($school, $lecturer);
        $assignment = $this->assignment($school, $course, $lecturer);
        $this->submission($school, $assignment, $student);
        Sanctum::actingAs($lecturer);

        $this->patchJson("/api/lecturer/assignments/{$assignment->id}", ['title' => 'Forbidden change'])
            ->assertForbidden();
        $this->deleteJson("/api/lecturer/assignments/{$assignment->id}")->assertForbidden();

        $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'title' => 'Assessment']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'assignment.updated', 'auditable_id' => $assignment->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'assignment.deleted', 'auditable_id' => $assignment->id]);
    }

    public function test_other_lecturer_and_cross_tenant_assignment_mutations_are_inaccessible(): void
    {
        [$school, $owner] = $this->schoolUser('lecturer');
        $otherLecturer = $this->user($school, 'lecturer');
        $course = $this->course($school, $owner);
        $assignment = $this->assignment($school, $course, $owner);
        Sanctum::actingAs($otherLecturer);

        $this->patchJson("/api/lecturer/assignments/{$assignment->id}", ['title' => 'IDOR'])->assertNotFound();
        $this->deleteJson("/api/lecturer/assignments/{$assignment->id}")->assertNotFound();

        [, $crossTenantLecturer] = $this->schoolUser('lecturer');
        Sanctum::actingAs($crossTenantLecturer);
        $this->patchJson("/api/lecturer/assignments/{$assignment->id}", ['title' => 'Cross tenant'])->assertNotFound();
        $this->deleteJson("/api/lecturer/assignments/{$assignment->id}")->assertNotFound();

        $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'title' => 'Assessment']);
    }

    public function test_assignment_attachment_can_be_replaced_and_removed_before_submissions(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $course = $this->course($school, $lecturer);
        Sanctum::actingAs($lecturer);

        $assignmentId = $this->post("/api/lecturer/courses/{$course->id}/assignments", [
            ...$this->assignmentPayload(),
            'file' => UploadedFile::fake()->createWithContent('original.txt', 'Original'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $oldPath = Assignment::query()->findOrFail($assignmentId)->file_path;

        $this->post("/api/lecturer/assignments/{$assignmentId}", [
            '_method' => 'PATCH',
            'title' => 'Assessment',
            'maximum_score' => 100,
            'target_type' => 'all',
            'file' => UploadedFile::fake()->create('replacement.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.attachment.file_name', 'replacement.pdf')
            ->assertJsonMissingPath('data.attachment.file_path');

        $assignment = Assignment::query()->findOrFail($assignmentId);
        $this->assertNotSame($oldPath, $assignment->file_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($assignment->file_path);
        $replacementPath = $assignment->file_path;
        $this->assertDatabaseHas('audit_logs', ['action' => 'assignment.file_replaced', 'auditable_id' => $assignmentId]);

        $this->patchJson("/api/lecturer/assignments/{$assignmentId}", ['remove_file' => true])
            ->assertOk()->assertJsonPath('data.attachment', null);

        Storage::disk('local')->assertMissing($replacementPath);
        $this->assertDatabaseHas('assignments', [
            'id' => $assignmentId,
            'file_path' => null,
            'file_name' => null,
            'file_mime_type' => null,
            'file_size' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'assignment.file_removed', 'auditable_id' => $assignmentId]);
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
        $course = new Course(['code' => 'CSC101', 'title' => 'Secure Systems']);
        $course->forceFill(['school_id' => $school->id, 'lecturer_id' => $lecturer->id])->save();

        return $course;
    }

    private function assignment(School $school, Course $course, User $lecturer): Assignment
    {
        $assignment = new Assignment($this->assignmentPayload());
        $assignment->forceFill(['school_id' => $school->id, 'course_id' => $course->id, 'created_by' => $lecturer->id])->save();

        return $assignment;
    }

    private function assignmentPayload(): array
    {
        return ['title' => 'Assessment', 'maximum_score' => 100, 'target_type' => 'all'];
    }

    private function submission(School $school, Assignment $assignment, User $student): AssignmentSubmission
    {
        $submission = new AssignmentSubmission(['submission_text' => 'Answer', 'submitted_at' => now()]);
        $submission->forceFill([
            'school_id' => $school->id,
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ])->save();

        return $submission;
    }
}
