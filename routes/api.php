<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Lecturer\AssignmentController as LecturerAssignmentController;
use App\Http\Controllers\Api\Lecturer\CourseController as LecturerCourseController;
use App\Http\Controllers\Api\Lecturer\GradeController as LecturerGradeController;
use App\Http\Controllers\Api\Lecturer\SubmissionController as LecturerSubmissionController;
use App\Http\Controllers\Api\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Api\Student\GradeController as StudentGradeController;
use App\Http\Controllers\Api\Student\SubmissionController as StudentSubmissionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::prefix('lecturer')->middleware('role:lecturer')->group(function (): void {
        Route::get('/courses', [LecturerCourseController::class, 'index']);
        Route::post('/courses', [LecturerCourseController::class, 'store']);
        Route::get('/courses/{course}/students', [LecturerCourseController::class, 'students']);
        Route::get('/courses/{course}/assignments', [LecturerAssignmentController::class, 'index']);
        Route::post('/courses/{course}/assignments', [LecturerAssignmentController::class, 'store']);
        Route::get('/assignments/{assignment}/submissions', [LecturerSubmissionController::class, 'index']);
        Route::post('/submissions/{submission}/grade', [LecturerGradeController::class, 'store']);
        Route::post('/assignments/{assignment}/release', [LecturerAssignmentController::class, 'release']);
    });

    Route::prefix('student')->middleware('role:student')->group(function (): void {
        Route::get('/assignments', [StudentAssignmentController::class, 'index']);
        Route::get('/assignments/{assignment}', [StudentAssignmentController::class, 'show']);
        Route::post('/assignments/{assignment}/submissions', [StudentSubmissionController::class, 'store'])
            ->middleware('throttle:submissions');
        Route::get('/submissions', [StudentSubmissionController::class, 'index']);
        Route::get('/submissions/{submission}', [StudentSubmissionController::class, 'show']);
        Route::get('/assignments/{assignment}/grade', [StudentGradeController::class, 'show']);
    });
});
