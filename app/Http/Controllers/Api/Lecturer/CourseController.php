<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Actions\Courses\CreateCourse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\StoreCourseRequest;
use App\Http\Resources\CourseResource;
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

    public function store(StoreCourseRequest $request, CreateCourse $createCourse): CourseResource
    {
        $course = $createCourse->execute($request->user(), $request->safe()->only([
            'code', 'title', 'description', 'is_active',
        ]));

        return CourseResource::make($course->load('lecturer:id,name'));
    }
}
