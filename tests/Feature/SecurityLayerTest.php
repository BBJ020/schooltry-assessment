<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\TestCase;

class SecurityLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_tenant_scope_hides_other_school_records_and_rejects_cross_tenant_writes(): void
    {
        [$schoolA, $lecturerA] = $this->schoolUserWithRole('lecturer');
        [$schoolB, $lecturerB] = $this->schoolUserWithRole('lecturer');
        $courseA = $this->course($schoolA, $lecturerA, 'A101');
        $courseB = $this->course($schoolB, $lecturerB, 'B101');

        app(TenantContext::class)->setFor($lecturerA);

        $this->assertTrue(Course::query()->whereKey($courseA->id)->exists());
        $this->assertFalse(Course::query()->whereKey($courseB->id)->exists());

        $this->expectException(LogicException::class);
        (new Course(['code' => 'BAD', 'title' => 'Cross tenant']))
            ->forceFill(['school_id' => $schoolA->id, 'lecturer_id' => $lecturerB->id])
            ->save();
    }

    public function test_lecturer_can_only_manage_owned_courses_in_their_school(): void
    {
        [$school, $owner] = $this->schoolUserWithRole('lecturer');
        $other = $this->user($school);
        $this->assignRole($other, 'lecturer');
        $owned = $this->course($school, $owner, 'OWN');
        $notOwned = $this->course($school, $other, 'OTHER');

        app(TenantContext::class)->setFor($owner);

        $this->assertTrue(Gate::forUser($owner)->allows('update', $owned));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $notOwned));
        $this->assertSame([$owned->id], Course::query()->visibleTo($owner)->pluck('id')->all());
    }

    public function test_student_can_only_access_their_own_submission(): void
    {
        [$school, $lecturer] = $this->schoolUserWithRole('lecturer');
        $student = $this->user($school);
        $otherStudent = $this->user($school);
        $this->assignRole($student, 'student');
        $this->assignRole($otherStudent, 'student');
        $course = $this->course($school, $lecturer, 'SEC');
        $course->students()->attach($student->id, ['school_id' => $school->id]);
        $course->students()->attach($otherStudent->id, ['school_id' => $school->id]);
        $assignment = $this->assignment($school, $course, $lecturer);
        $own = $this->submission($school, $assignment, $student);
        $other = $this->submission($school, $assignment, $otherStudent);

        app(TenantContext::class)->setFor($student);

        $this->assertTrue(Gate::forUser($student)->allows('view', $own));
        $this->assertFalse(Gate::forUser($student)->allows('view', $other));
        $this->assertSame([$own->id], AssignmentSubmission::query()->visibleTo($student)->pluck('id')->all());
    }

    public function test_grades_are_not_visible_until_the_assignment_is_released(): void
    {
        [$school, $lecturer] = $this->schoolUserWithRole('lecturer');
        $student = $this->user($school);
        $this->assignRole($student, 'student');
        $course = $this->course($school, $lecturer, 'GRD');
        $course->students()->attach($student->id, ['school_id' => $school->id]);
        $assignment = $this->assignment($school, $course, $lecturer);
        $submission = $this->submission($school, $assignment, $student);
        $grade = (new Grade(['score' => 80, 'graded_at' => now()]))->forceFill([
            'school_id' => $school->id,
            'assignment_id' => $assignment->id,
            'submission_id' => $submission->id,
            'student_id' => $student->id,
            'graded_by' => $lecturer->id,
        ]);
        $grade->save();

        app(TenantContext::class)->setFor($student);
        $this->assertFalse(Gate::forUser($student)->allows('view', $grade));
        $this->assertSame(0, Grade::query()->visibleTo($student)->count());

        $assignment->update(['is_released' => true, 'released_at' => now()]);
        $this->assertTrue(Gate::forUser($student)->allows('view', $grade));
        $this->assertSame(1, Grade::query()->visibleTo($student)->count());
    }

    public function test_audit_logs_are_append_only_through_eloquent(): void
    {
        [$school, $actor] = $this->schoolUserWithRole('admin');
        app(TenantContext::class)->setFor($actor);
        $log = new AuditLog(['action' => 'test.created']);
        $log->forceFill(['school_id' => $school->id, 'actor_id' => $actor->id])->save();

        try {
            $log->update(['action' => 'tampered']);
            $this->fail('Updating an audit log should throw.');
        } catch (LogicException) {
            $this->assertSame('test.created', $log->fresh()->action);
        }

        try {
            AuditLog::query()->whereKey($log->id)->update(['action' => 'builder-tampered']);
            $this->fail('Bulk-updating audit logs should throw.');
        } catch (LogicException) {
            $this->assertSame('test.created', $log->fresh()->action);
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    /** @return array{School, User} */
    private function schoolUserWithRole(string $role): array
    {
        $school = School::query()->create(['name' => fake()->company(), 'slug' => fake()->unique()->slug()]);
        $user = $this->user($school);
        $this->assignRole($user, $role);

        return [$school, $user];
    }

    private function user(School $school): User
    {
        $user = User::factory()->make();
        $user->forceFill(['school_id' => $school->id, 'is_active' => true])->save();

        return $user;
    }

    private function assignRole(User $user, string $name): void
    {
        $role = Role::query()->firstOrCreate(['name' => $name]);
        $user->roles()->attach($role->id, ['school_id' => $user->school_id]);
    }

    private function course(School $school, User $lecturer, string $code): Course
    {
        $course = new Course(['code' => $code, 'title' => "Course {$code}"]);
        $course->forceFill(['school_id' => $school->id, 'lecturer_id' => $lecturer->id])->save();

        return $course;
    }

    private function assignment(School $school, Course $course, User $creator): Assignment
    {
        $assignment = new Assignment(['title' => 'Assessment', 'target_type' => 'all']);
        $assignment->forceFill(['school_id' => $school->id, 'course_id' => $course->id, 'created_by' => $creator->id])->save();

        return $assignment;
    }

    private function submission(School $school, Assignment $assignment, User $student): AssignmentSubmission
    {
        $submission = new AssignmentSubmission(['submission_text' => 'Answer', 'submitted_at' => now()]);
        $submission->forceFill(['school_id' => $school->id, 'assignment_id' => $assignment->id, 'student_id' => $student->id])->save();

        return $submission;
    }
}
