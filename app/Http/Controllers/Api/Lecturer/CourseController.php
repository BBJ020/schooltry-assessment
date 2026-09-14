<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\StudentOptionResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', Course::class);

        return CourseResource::collection(
            Course::query()->visibleTo($request->user())->with('lecturer:id,name')->latest()->paginate()
        );
    }

    public function students(Request $request, int $course): AnonymousResourceCollection
    {
        $ownedCourse = Course::query()->visibleTo($request->user())->findOrFail($course);
        Gate::forUser($request->user())->authorize('update', $ownedCourse);

        return StudentOptionResource::collection(
            $ownedCourse->students()->select(['users.id', 'users.name'])->orderBy('users.name')->paginate(100)
        );
    }
}
