<?php

use App\Http\Controllers\Api\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\DirectoryController as AdminDirectoryController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AssignmentAttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Lecturer\AssignmentController as LecturerAssignmentController;
use App\Http\Controllers\Api\Lecturer\CourseController as LecturerCourseController;
use App\Http\Controllers\Api\Lecturer\GradeController as LecturerGradeController;
use App\Http\Controllers\Api\Lecturer\SubmissionController as LecturerSubmissionController;
use App\Http\Controllers\Api\Management\UserMutationController;
use App\Http\Controllers\Api\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Api\Student\GradeController as StudentGradeController;
use App\Http\Controllers\Api\Student\SubmissionController as StudentSubmissionController;
use App\Http\Controllers\Api\Superadmin\DashboardController as SuperadminDashboardController;
use App\Http\Controllers\Api\Superadmin\SchoolAdminController;
use App\Http\Controllers\Api\Superadmin\SchoolController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::middleware(['auth:sanctum', 'role:superadmin', 'platform'])->prefix('superadmin')->group(function (): void {
    Route::get('/dashboard', SuperadminDashboardController::class);
    Route::get('/schools', [SchoolController::class, 'index']);
    Route::post('/schools', [SchoolController::class, 'store']);
    Route::get('/schools/{school}', [SchoolController::class, 'show']);
    Route::put('/schools/{school}', [SchoolController::class, 'update']);
    Route::post('/schools/{school}/activate', [SchoolController::class, 'activate']);
    Route::post('/schools/{school}/deactivate', [SchoolController::class, 'deactivate']);
    Route::get('/schools/{school}/admins', [SchoolAdminController::class, 'index']);
    Route::post('/schools/{school}/admins', [SchoolAdminController::class, 'store']);
    Route::post('/schools/{school}/admins/assign', [SchoolAdminController::class, 'assign']);
    Route::delete('/schools/{school}/admins/{user}', [SchoolAdminController::class, 'destroy']);
    Route::put('/users/{user}', [UserMutationController::class, 'update']);
    Route::put('/users/{user}/role', [UserMutationController::class, 'role']);
    Route::post('/users/{user}/activate', [UserMutationController::class, 'activate']);
    Route::post('/users/{user}/deactivate', [UserMutationController::class, 'deactivate']);
    Route::post('/users/{user}/reset-password', [UserMutationController::class, 'resetPassword']);
});

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::prefix('admin')->middleware('role:admin')->group(function (): void {
        Route::get('/dashboard', AdminDashboardController::class);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::put('/users/{user}', [UserMutationController::class, 'update']);
        Route::post('/users/{user}/activate', [UserMutationController::class, 'activate']);
        Route::post('/users/{user}/deactivate', [UserMutationController::class, 'deactivate']);
        Route::put('/users/{user}/role', [UserMutationController::class, 'role']);
        Route::post('/users/{user}/reset-password', [UserMutationController::class, 'resetPassword']);
        Route::get('/students', [AdminDirectoryController::class, 'students']);
        Route::get('/lecturers', [AdminDirectoryController::class, 'lecturers']);
        Route::get('/admins', [AdminDirectoryController::class, 'admins']);
        Route::get('/courses', [AdminCourseController::class, 'index']);
        Route::post('/courses', [AdminCourseController::class, 'store']);
        Route::get('/courses/{course}', [AdminCourseController::class, 'show']);
        Route::put('/courses/{course}', [AdminCourseController::class, 'update']);
        Route::get('/courses/{course}/students', [AdminCourseController::class, 'students']);
        Route::post('/courses/{course}/students', [AdminCourseController::class, 'enroll']);
        Route::delete('/courses/{course}/students/{student}', [AdminCourseController::class, 'unenroll']);
    });

    Route::prefix('lecturer')->middleware('role:lecturer')->group(function (): void {
        Route::get('/courses', [LecturerCourseController::class, 'index']);
        Route::get('/courses/{course}/students', [LecturerCourseController::class, 'students']);
        Route::get('/courses/{course}/assignments', [LecturerAssignmentController::class, 'index']);
        Route::post('/courses/{course}/assignments', [LecturerAssignmentController::class, 'store']);
        Route::match(['put', 'patch'], '/assignments/{assignment}', [LecturerAssignmentController::class, 'update']);
        Route::delete('/assignments/{assignment}', [LecturerAssignmentController::class, 'destroy']);
        Route::get('/assignments/{assignment}/attachment', AssignmentAttachmentController::class);
        Route::get('/assignments/{assignment}/submissions', [LecturerSubmissionController::class, 'index']);
        Route::post('/submissions/{submission}/grade', [LecturerGradeController::class, 'store']);
        Route::post('/assignments/{assignment}/release', [LecturerAssignmentController::class, 'release']);
    });

    Route::prefix('student')->middleware('role:student')->group(function (): void {
        Route::get('/assignments', [StudentAssignmentController::class, 'index']);
        Route::get('/assignments/{assignment}', [StudentAssignmentController::class, 'show']);
        Route::get('/assignments/{assignment}/attachment', AssignmentAttachmentController::class);
        Route::post('/assignments/{assignment}/submissions', [StudentSubmissionController::class, 'store'])
            ->middleware('throttle:submissions');
        Route::get('/submissions', [StudentSubmissionController::class, 'index']);
        Route::get('/submissions/{submission}', [StudentSubmissionController::class, 'show']);
        Route::get('/assignments/{assignment}/grade', [StudentGradeController::class, 'show']);
    });
});
