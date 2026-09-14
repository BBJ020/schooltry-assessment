<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManagementSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['superadmin', 'admin', 'lecturer', 'student'] as $role) {
            Role::query()->create(['name' => $role]);
        }
    }

    public function test_superadmin_manages_schools_and_school_admins_with_auditing(): void
    {
        $superadmin = $this->platformUser();
        Sanctum::actingAs($superadmin);

        $schoolId = $this->postJson('/api/superadmin/schools', ['name' => 'North School', 'slug' => 'north', 'email' => 'north@example.test'])
            ->assertCreated()->assertJsonPath('data.name', 'North School')->json('data.id');
        $this->getJson('/api/superadmin/schools')->assertOk()->assertJsonPath('data.0.id', $schoolId);
        $this->putJson("/api/superadmin/schools/{$schoolId}", ['name' => 'North Academy'])->assertOk()->assertJsonPath('data.name', 'North Academy');
        $this->postJson("/api/superadmin/schools/{$schoolId}/deactivate")->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson("/api/superadmin/schools/{$schoolId}/activate")->assertOk()->assertJsonPath('data.is_active', true);

        $response = $this->postJson("/api/superadmin/schools/{$schoolId}/admins", [
            'name' => 'North Admin', 'email' => 'north.admin@example.test', 'school_id' => 999,
        ])->assertUnprocessable()->assertJsonMissingPath('temporary_password');
        $response->assertJsonValidationErrors('school_id');

        $created = $this->postJson("/api/superadmin/schools/{$schoolId}/admins", ['name' => 'North Admin', 'email' => 'north.admin@example.test'])
            ->assertCreated()->assertJsonPath('data.roles.0', 'admin')->assertJsonStructure(['temporary_password'])->json();
        $this->assertTrue(Hash::check($created['temporary_password'], User::query()->findOrFail($created['data']['id'])->password));
        $this->assertDatabaseHas('role_user', ['school_id' => $schoolId, 'user_id' => $created['data']['id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'school.created', 'school_id' => $schoolId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'school.updated', 'school_id' => $schoolId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'school.deactivated', 'school_id' => $schoolId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'school.activated', 'school_id' => $schoolId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'school_admin.created', 'school_id' => $schoolId]);
        $this->assertDatabaseMissing('audit_logs', ['new_values' => $created['temporary_password']]);
    }

    public function test_active_schoolless_superadmin_can_login_without_tenant_context(): void
    {
        $superadmin = $this->platformUser();
        $superadmin->forceFill(['password' => 'platform-password'])->save();

        $this->postJson('/api/login', ['email' => $superadmin->email, 'password' => 'platform-password'])
            ->assertOk()->assertJsonPath('user.school', null)->assertJsonPath('user.roles.0', 'superadmin');
        $this->assertDatabaseHas('role_user', ['user_id' => $superadmin->id, 'school_id' => null]);

        Sanctum::actingAs($superadmin);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.roles.0', 'superadmin');
        $this->postJson('/api/logout')->assertOk();
    }

    public function test_superadmin_can_deliberately_assign_an_eligible_user_but_not_cross_assign(): void
    {
        $superadmin = $this->platformUser();
        $schoolA = $this->school('a');
        $schoolB = $this->school('b');
        $eligible = User::factory()->create(['school_id' => null]);
        $otherStudent = $this->tenantUser($schoolB, 'student');
        Sanctum::actingAs($superadmin);

        $this->postJson("/api/superadmin/schools/{$schoolA->id}/admins/assign", ['user_id' => $eligible->id, 'school_id' => $schoolB->id])
            ->assertUnprocessable()->assertJsonValidationErrors('school_id');
        $this->postJson("/api/superadmin/schools/{$schoolA->id}/admins/assign", ['user_id' => $eligible->id])
            ->assertOk()->assertJsonPath('data.school_id', $schoolA->id)->assertJsonPath('data.roles.0', 'admin');
        $this->postJson("/api/superadmin/schools/{$schoolA->id}/admins/assign", ['user_id' => $otherStudent->id])
            ->assertUnprocessable();
    }

    public function test_school_admin_user_management_is_tenant_scoped_and_cannot_create_superadmin(): void
    {
        $schoolA = $this->school('a');
        $schoolB = $this->school('b');
        $admin = $this->tenantUser($schoolA, 'admin');
        $other = $this->tenantUser($schoolB, 'student');
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')->assertOk()->assertJsonMissing(['email' => $other->email]);
        $this->getJson("/api/admin/users/{$other->id}")->assertNotFound();
        $this->putJson("/api/admin/users/{$other->id}", ['name' => 'Compromised'])->assertNotFound();
        $this->postJson('/api/admin/users', ['name' => 'Bad', 'email' => 'bad@example.test', 'role' => 'superadmin'])->assertUnprocessable();

        foreach (['student', 'lecturer', 'admin'] as $role) {
            $result = $this->postJson('/api/admin/users', ['name' => ucfirst($role), 'email' => "{$role}@a.test", 'role' => $role, 'school_id' => $schoolB->id]);
            $result->assertUnprocessable()->assertJsonValidationErrors('school_id');
            $created = $this->postJson('/api/admin/users', ['name' => ucfirst($role), 'email' => "{$role}@a.test", 'role' => $role])
                ->assertCreated()->assertJsonPath('data.school_id', $schoolA->id)->json('data');
            $this->assertDatabaseHas('role_user', ['school_id' => $schoolA->id, 'user_id' => $created['id']]);
        }

        $student = User::query()->where('email', 'student@a.test')->firstOrFail();
        $this->postJson("/api/admin/users/{$student->id}/deactivate")->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson("/api/admin/users/{$student->id}/activate")->assertOk()->assertJsonPath('data.is_active', true);
        $this->putJson("/api/admin/users/{$student->id}/role", ['role' => 'lecturer'])->assertOk()->assertJsonPath('data.roles.0', 'lecturer');
        $reset = $this->postJson("/api/admin/users/{$student->id}/reset-password")->assertOk()->assertJsonStructure(['temporary_password'])->json();
        $this->assertTrue(Hash::check($reset['temporary_password'], $student->refresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_reset', 'school_id' => $schoolA->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'school_id' => $schoolA->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_changed', 'school_id' => $schoolA->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivated', 'school_id' => $schoolA->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.activated', 'school_id' => $schoolA->id]);
    }

    public function test_admin_course_and_enrollment_operations_enforce_school_and_schema(): void
    {
        $schoolA = $this->school('a');
        $schoolB = $this->school('b');
        $admin = $this->tenantUser($schoolA, 'admin');
        $lecturer = $this->tenantUser($schoolA, 'lecturer');
        $student = $this->tenantUser($schoolA, 'student');
        $otherStudent = $this->tenantUser($schoolB, 'student');
        $otherLecturer = $this->tenantUser($schoolB, 'lecturer');
        $otherCourse = $this->course($schoolB, $otherLecturer, 'OTHER');
        Sanctum::actingAs($admin);

        $courseId = $this->postJson('/api/admin/courses', ['code' => 'SEC101', 'title' => 'Security', 'lecturer_id' => $lecturer->id, 'school_id' => $schoolB->id])
            ->assertUnprocessable()->json('data.id');
        $courseId = $this->postJson('/api/admin/courses', ['code' => 'SEC101', 'title' => 'Security', 'lecturer_id' => $lecturer->id])
            ->assertCreated()->assertJsonPath('data.lecturer.id', $lecturer->id)->json('data.id');
        $this->getJson("/api/admin/courses/{$otherCourse->id}")->assertNotFound();
        $this->postJson("/api/admin/courses/{$courseId}/students", ['student_ids' => [$otherStudent->id]])->assertUnprocessable();
        $this->postJson("/api/admin/courses/{$courseId}/students", ['student_ids' => [$student->id]])->assertNoContent();
        $this->postJson("/api/admin/courses/{$courseId}/students", ['student_ids' => [$student->id]])->assertUnprocessable();
        $this->assertDatabaseHas('course_student', ['school_id' => $schoolA->id, 'course_id' => $courseId, 'student_id' => $student->id]);
        $this->assertFalse(Schema::hasColumn('course_student', 'created_at'));
        $this->assertFalse(Schema::hasColumn('course_student', 'updated_at'));
        $this->deleteJson("/api/admin/courses/{$courseId}/students/{$student->id}")->assertNoContent();
        $this->assertDatabaseMissing('course_student', ['course_id' => $courseId, 'student_id' => $student->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'course.students_enrolled']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'course.student_unenrolled']);
    }

    public function test_management_route_role_boundaries_and_unauthenticated_responses(): void
    {
        $school = $this->school('roles');
        $this->getJson('/api/superadmin/dashboard')->assertUnauthorized();
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();

        foreach (['admin', 'lecturer', 'student'] as $role) {
            Sanctum::actingAs($this->tenantUser($school, $role));
            $this->getJson('/api/superadmin/dashboard')->assertForbidden();
        }
        foreach (['lecturer', 'student'] as $role) {
            Sanctum::actingAs($this->tenantUser($school, $role));
            $this->getJson('/api/admin/dashboard')->assertForbidden();
        }

        Sanctum::actingAs($this->platformUser());
        $this->getJson('/api/admin/dashboard')->assertForbidden();
        $this->getJson('/api/superadmin/dashboard')->assertOk()->assertJsonStructure(['data' => ['total_schools', 'active_schools', 'inactive_schools', 'total_admins', 'total_lecturers', 'total_students', 'total_courses']]);
    }

    private function school(string $suffix): School
    {
        return School::query()->create(['name' => "School {$suffix}", 'slug' => "school-{$suffix}", 'is_active' => true]);
    }

    private function platformUser(): User
    {
        $user = User::factory()->create(['school_id' => null, 'is_active' => true]);
        $this->attachRole($user, 'superadmin', null);

        return $user;
    }

    private function tenantUser(School $school, string $role): User
    {
        $user = User::factory()->create(['school_id' => $school->id, 'is_active' => true]);
        $this->attachRole($user, $role, (int) $school->id);

        return $user;
    }

    private function attachRole(User $user, string $role, ?int $schoolId): void
    {
        DB::table('role_user')->insert(['school_id' => $schoolId, 'role_id' => Role::query()->where('name', $role)->value('id'), 'user_id' => $user->id]);
    }

    private function course(School $school, User $lecturer, string $code): Course
    {
        $course = new Course(['code' => $code, 'title' => $code, 'is_active' => true]);
        $course->forceFill(['school_id' => $school->id, 'lecturer_id' => $lecturer->id])->save();

        return $course;
    }
}
