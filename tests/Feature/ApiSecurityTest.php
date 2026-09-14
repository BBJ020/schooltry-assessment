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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_login_returns_a_safe_user_and_token(): void
    {
        [$school, $user] = $this->schoolUser('student', 'correct-password');

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'device_name' => 'test-suite',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.school.id', $school->id)
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token')
            ->assertJsonStructure(['access_token', 'user' => ['id', 'name', 'email', 'school', 'roles']]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_invalid_login_is_rejected(): void
    {
        [, $user] = $this->schoolUser('student', 'correct-password');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertJsonMissingPath('access_token');
    }

    public function test_inactive_user_is_rejected(): void
    {
        [, $user] = $this->schoolUser('student', 'correct-password');
        $user->forceFill(['is_active' => false])->save();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertForbidden()
            ->assertJsonMissingPath('access_token');
    }

    public function test_user_from_an_inactive_school_is_rejected(): void
    {
        [$school, $user] = $this->schoolUser('student', 'correct-password');
        $school->update(['is_active' => false]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertForbidden()
            ->assertJsonMissingPath('access_token');
    }

    public function test_cross_tenant_course_is_inaccessible(): void
    {
        [, $lecturer] = $this->schoolUser('lecturer');
        [$otherSchool, $otherLecturer] = $this->schoolUser('lecturer');
        $otherCourse = $this->course($otherSchool, $otherLecturer, 'OTHER');
        Sanctum::actingAs($lecturer);

        $this->postJson("/api/lecturer/courses/{$otherCourse->id}/assignments", $this->assignmentPayload())
            ->assertNotFound()
            ->assertJsonMissingPath('id');
    }

    public function test_lecturer_course_creation_endpoint_is_not_available(): void
    {
        [, $lecturer] = $this->schoolUser('lecturer');
        Sanctum::actingAs($lecturer);

        $this->postJson('/api/lecturer/courses', [
            'code' => 'FORBIDDEN',
            'title' => 'Lecturer-created course',
        ])->assertMethodNotAllowed();

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_lecturer_course_assignments_and_roster_are_limited_to_owned_courses(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $otherLecturer = $this->user($school, 'lecturer');
        $student = $this->user($school, 'student');
        $ownedCourse = $this->course($school, $lecturer, 'OWNED');
        $otherCourse = $this->course($school, $otherLecturer, 'NOTOWNED');
        $this->enroll($ownedCourse, $student);
        $assignment = $this->assignment($school, $ownedCourse, $lecturer);
        Sanctum::actingAs($lecturer);

        $this->getJson("/api/lecturer/courses/{$ownedCourse->id}/assignments")
            ->assertOk()
            ->assertJsonPath('data.0.id', $assignment->id);
        $this->getJson("/api/lecturer/courses/{$ownedCourse->id}/students")
            ->assertOk()
            ->assertJsonPath('data.0.id', $student->id)
            ->assertJsonMissingPath('data.0.email');
        $this->getJson("/api/lecturer/courses/{$otherCourse->id}/assignments")->assertNotFound();
        $this->getJson("/api/lecturer/courses/{$otherCourse->id}/students")->assertNotFound();
    }

    public function test_student_cannot_access_another_students_submission(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $student = $this->user($school, 'student');
        $otherStudent = $this->user($school, 'student');
        $course = $this->course($school, $lecturer);
        $this->enroll($course, $student, $otherStudent);
        $assignment = $this->assignment($school, $course, $lecturer);
        $otherSubmission = $this->submission($school, $assignment, $otherStudent);
        Sanctum::actingAs($student);

        $this->getJson("/api/student/submissions/{$otherSubmission->id}")
            ->assertNotFound()
            ->assertJsonMissingPath('data.submission_text');
    }

    public function test_lecturer_cannot_grade_another_lecturers_course(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $otherLecturer = $this->user($school, 'lecturer');
        $student = $this->user($school, 'student');
        $course = $this->course($school, $otherLecturer);
        $this->enroll($course, $student);
        $assignment = $this->assignment($school, $course, $otherLecturer);
        $submission = $this->submission($school, $assignment, $student);
        Sanctum::actingAs($lecturer);

        $this->postJson("/api/lecturer/submissions/{$submission->id}/grade", ['score' => 75])
            ->assertNotFound();
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_student_cannot_see_unreleased_grade_but_can_see_it_after_release(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $student = $this->user($school, 'student');
        $course = $this->course($school, $lecturer);
        $this->enroll($course, $student);
        $assignment = $this->assignment($school, $course, $lecturer);
        $submission = $this->submission($school, $assignment, $student);
        $this->grade($school, $assignment, $submission, $student, $lecturer);
        Sanctum::actingAs($student);

        $this->getJson("/api/student/assignments/{$assignment->id}/grade")
            ->assertNotFound()
            ->assertJsonMissing(['score' => 88])
            ->assertJsonMissing(['feedback' => 'Good work']);

        $assignment->forceFill(['is_released' => true, 'released_at' => now(), 'released_by' => $lecturer->id])->save();

        $this->getJson("/api/student/assignments/{$assignment->id}/grade")
            ->assertOk()
            ->assertJsonPath('data.score', '88.00')
            ->assertJsonPath('data.feedback', 'Good work');
    }

    public function test_selected_and_all_student_targeting_are_enforced_by_collection_queries(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $selectedStudent = $this->user($school, 'student');
        $otherStudent = $this->user($school, 'student');
        $course = $this->course($school, $lecturer);
        $this->enroll($course, $selectedStudent, $otherStudent);
        Sanctum::actingAs($lecturer);

        $selectedId = $this->postJson("/api/lecturer/courses/{$course->id}/assignments", [
            ...$this->assignmentPayload(),
            'title' => 'Selected only',
            'target_type' => 'selected',
            'target_student_ids' => [$selectedStudent->id],
        ])->assertCreated()->json('data.id');

        $allId = $this->postJson("/api/lecturer/courses/{$course->id}/assignments", [
            ...$this->assignmentPayload(),
            'title' => 'Everyone',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($selectedStudent);
        $selectedResponse = $this->getJson('/api/student/assignments')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$selectedId, $allId], array_column($selectedResponse, 'id'));

        Sanctum::actingAs($otherStudent);
        $this->getJson('/api/student/assignments')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $allId);
    }

    public function test_lecturer_can_create_assignment_with_a_private_file_and_server_derived_metadata(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $course = $this->course($school, $lecturer);
        Sanctum::actingAs($lecturer);

        $response = $this->post("/api/lecturer/courses/{$course->id}/assignments", [
            ...$this->assignmentPayload(),
            'file' => UploadedFile::fake()->create('course outline.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.attachment.file_name', 'course outline.pdf')
            ->assertJsonMissingPath('data.file_path')
            ->assertJsonMissingPath('data.attachment.file_path');

        $assignment = Assignment::query()->findOrFail($response->json('data.id'));
        $this->assertMatchesRegularExpression(
            "#^assignments/{$school->id}/{$course->id}/{$assignment->id}/[0-9a-f-]+\\.pdf$#",
            $assignment->file_path,
        );
        $this->assertSame('course outline.pdf', $assignment->file_name);
        $this->assertSame('application/pdf', $assignment->file_mime_type);
        $this->assertSame(102400, $assignment->file_size);
        Storage::disk('local')->assertExists($assignment->file_path);
        $this->assertSame('private', config('filesystems.disks.local.visibility'));
    }

    public function test_lecturer_can_create_assignment_without_a_file(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $course = $this->course($school, $lecturer);
        Sanctum::actingAs($lecturer);

        $assignmentId = $this->postJson("/api/lecturer/courses/{$course->id}/assignments", $this->assignmentPayload())
            ->assertCreated()
            ->assertJsonPath('data.attachment', null)
            ->json('data.id');

        $this->assertDatabaseHas('assignments', [
            'id' => $assignmentId,
            'file_path' => null,
            'file_name' => null,
            'file_mime_type' => null,
            'file_size' => null,
        ]);
    }

    public function test_invalid_assignment_file_is_rejected(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $course = $this->course($school, $lecturer);
        Sanctum::actingAs($lecturer);

        $this->post("/api/lecturer/courses/{$course->id}/assignments", [
            ...$this->assignmentPayload(),
            'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_oversized_assignment_file_is_rejected(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $course = $this->course($school, $lecturer);
        Sanctum::actingAs($lecturer);

        $this->post("/api/lecturer/courses/{$course->id}/assignments", [
            ...$this->assignmentPayload(),
            'file' => UploadedFile::fake()->create('too-large.pdf', 20481, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_unauthorized_lecturer_cannot_attach_to_or_download_from_another_course(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
        [$school, $owner] = $this->schoolUser('lecturer');
        $otherLecturer = $this->user($school, 'lecturer');
        $course = $this->course($school, $owner);
        Sanctum::actingAs($otherLecturer);

        $this->post("/api/lecturer/courses/{$course->id}/assignments", [
            ...$this->assignmentPayload(),
            'file' => UploadedFile::fake()->create('private.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertNotFound();

        $assignment = $this->assignment($school, $course, $owner);
        $this->getJson("/api/lecturer/assignments/{$assignment->id}/attachment")->assertNotFound();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_authorized_student_can_view_and_download_an_assignment_attachment(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $student = $this->user($school, 'student');
        $course = $this->course($school, $lecturer);
        $this->enroll($course, $student);
        Sanctum::actingAs($lecturer);
        $assignmentId = $this->post("/api/lecturer/courses/{$course->id}/assignments", [
            ...$this->assignmentPayload(),
            'file' => UploadedFile::fake()->createWithContent('reading.txt', 'Read chapter one.'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        Sanctum::actingAs($student);
        $this->getJson("/api/student/assignments/{$assignmentId}")
            ->assertOk()
            ->assertJsonPath('data.attachment.file_name', 'reading.txt')
            ->assertJsonMissingPath('data.attachment.file_path');
        $this->get("/api/student/assignments/{$assignmentId}/attachment", ['Accept' => 'application/octet-stream'])
            ->assertOk();
    }

    public function test_untargeted_student_cannot_access_an_assignment_attachment(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $student = $this->user($school, 'student');
        $course = $this->course($school, $lecturer);
        $assignment = $this->assignment($school, $course, $lecturer);
        $assignment->forceFill(['file_path' => 'assignments/private.pdf', 'file_name' => 'private.pdf'])->save();
        Sanctum::actingAs($student);

        $this->getJson("/api/student/assignments/{$assignment->id}/attachment")->assertNotFound();
    }

    public function test_cross_tenant_student_cannot_access_an_assignment_attachment(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        [, $otherStudent] = $this->schoolUser('student');
        $course = $this->course($school, $lecturer);
        $assignment = $this->assignment($school, $course, $lecturer);
        $assignment->forceFill(['file_path' => 'assignments/private.pdf', 'file_name' => 'private.pdf'])->save();
        Sanctum::actingAs($otherStudent);

        $this->getJson("/api/student/assignments/{$assignment->id}/attachment")->assertNotFound();
    }

    public function test_assignment_file_storage_metadata_cannot_be_supplied_by_the_browser(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $course = $this->course($school, $lecturer);
        Sanctum::actingAs($lecturer);

        $this->postJson("/api/lecturer/courses/{$course->id}/assignments", [
            ...$this->assignmentPayload(),
            'file_path' => 'public/attacker-controlled.pdf',
            'file_name' => 'trusted.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => 10,
            'school_id' => 999,
            'course_id' => 999,
            'lecturer_id' => $lecturer->id,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'file_path', 'file_name', 'file_mime_type', 'file_size', 'school_id', 'course_id', 'lecturer_id',
        ]);

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_submission_endpoint_is_rate_limited(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $student = $this->user($school, 'student');
        $course = $this->course($school, $lecturer);
        $this->enroll($course, $student);
        $assignment = $this->assignment($school, $course, $lecturer);
        Sanctum::actingAs($student);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson("/api/student/assignments/{$assignment->id}/submissions", [
                'submission_text' => "Attempt {$attempt}",
            ])->assertSuccessful();
        }

        $this->postJson("/api/student/assignments/{$assignment->id}/submissions", [
            'submission_text' => 'Attempt 6',
        ])->assertStatus(429);
    }

    public function test_mass_assignment_fields_are_rejected(): void
    {
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $student = $this->user($school, 'student');
        $course = $this->course($school, $lecturer);
        $this->enroll($course, $student);
        $assignment = $this->assignment($school, $course, $lecturer);
        Sanctum::actingAs($student);

        $this->postJson("/api/student/assignments/{$assignment->id}/submissions", [
            'submission_text' => 'Attempted override',
            'school_id' => 999,
            'student_id' => $lecturer->id,
            'file_path' => 'public/attacker-controlled.php',
            'mime_type' => 'application/x-php',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['school_id', 'student_id', 'file_path', 'mime_type']);

        $this->assertDatabaseCount('assignment_submissions', 0);
    }

    public function test_uploaded_file_metadata_and_private_s3_key_are_derived_server_side(): void
    {
        config(['filesystems.default' => 's3']);
        Storage::fake('s3');
        [$school, $lecturer] = $this->schoolUser('lecturer');
        $student = $this->user($school, 'student');
        $course = $this->course($school, $lecturer);
        $this->enroll($course, $student);
        $assignment = $this->assignment($school, $course, $lecturer);
        Sanctum::actingAs($student);

        $response = $this->post("/api/student/assignments/{$assignment->id}/submissions", [
            'submission_text' => 'See attachment.',
            'file' => UploadedFile::fake()->create('my answer.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $submission = AssignmentSubmission::query()->firstOrFail();
        $this->assertStringStartsWith("schools/{$school->id}/assignments/{$assignment->id}/", $submission->file_path);
        $this->assertSame('application/pdf', $submission->mime_type);
        $this->assertSame(102400, $submission->file_size);
        Storage::disk('s3')->assertExists($submission->file_path);
        $this->assertSame('private', config('filesystems.disks.s3.visibility'));
        $response->assertJsonMissingPath('data.file_path');
    }

    /** @return array{School, User} */
    private function schoolUser(string $role, string $password = 'password'): array
    {
        $school = School::query()->create(['name' => fake()->company(), 'slug' => fake()->unique()->slug()]);

        return [$school, $this->user($school, $role, $password)];
    }

    private function user(School $school, string $role, string $password = 'password'): User
    {
        $user = User::factory()->make();
        $user->forceFill(['school_id' => $school->id, 'password' => Hash::make($password), 'is_active' => true])->save();
        $roleModel = Role::query()->firstOrCreate(['name' => $role]);
        $user->roles()->attach($roleModel->id, ['school_id' => $school->id]);

        return $user;
    }

    private function course(School $school, User $lecturer, string $code = 'CSC101'): Course
    {
        $course = new Course(['code' => $code, 'title' => 'Secure Systems']);
        $course->forceFill(['school_id' => $school->id, 'lecturer_id' => $lecturer->id])->save();

        return $course;
    }

    private function enroll(Course $course, User ...$students): void
    {
        foreach ($students as $student) {
            $course->students()->attach($student->id, ['school_id' => $course->school_id]);
        }
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
        $submission->forceFill(['school_id' => $school->id, 'assignment_id' => $assignment->id, 'student_id' => $student->id])->save();

        return $submission;
    }

    private function grade(
        School $school,
        Assignment $assignment,
        AssignmentSubmission $submission,
        User $student,
        User $lecturer,
    ): Grade {
        $grade = new Grade(['score' => 88, 'feedback' => 'Good work', 'graded_at' => now()]);
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
