<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Management\ManageCourse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\EnrollStudentsRequest;
use App\Http\Requests\Management\StatusRequest;
use App\Http\Requests\Management\StoreAdminCourseRequest;
use App\Http\Requests\Management\UpdateAdminCourseRequest;
use App\Http\Resources\CourseResource;
use App\Http\Resources\ManagementUserResource;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Course::class);
        $search = trim((string) $request->query('search'));

        return CourseResource::collection(Course::query()->visibleTo($request->user())->with('lecturer:id,name')->withCount('students')
            ->when($search, fn ($query) => $query->where(fn ($inner) => $inner->where('code', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")))
            ->orderBy('code')->paginate(25)->withQueryString());
    }

    public function store(StoreAdminCourseRequest $request, ManageCourse $action): CourseResource
    {
        $lecturer = User::query()->findOrFail($request->integer('lecturer_id'));
        $data = $request->safe()->except(['lecturer_id']);

        return CourseResource::make($action->create($request->user(), $lecturer, $data)->load('lecturer:id,name'));
    }

    public function show(Course $course): CourseResource
    {
        Gate::authorize('view', $course);

        return CourseResource::make($course->load(['lecturer:id,name', 'students.roles:id,name'])->loadCount('students'));
    }

    public function update(UpdateAdminCourseRequest $request, Course $course, ManageCourse $action): CourseResource
    {
        $lecturer = User::query()->findOrFail($request->integer('lecturer_id'));

        return CourseResource::make($action->update($request->user(), $course, $lecturer, $request->safe()->except(['lecturer_id'])));
    }

    public function students(Course $course): AnonymousResourceCollection
    {
        Gate::authorize('view', $course);

        return ManagementUserResource::collection($course->students()->with('roles:id,name')->orderBy('name')->get());
    }

    public function enroll(EnrollStudentsRequest $request, Course $course, ManageCourse $action): Response
    {
        $action->enroll($request->user(), $course, $request->validated('student_ids'));

        return response()->noContent();
    }

    public function unenroll(StatusRequest $request, Course $course, User $student, ManageCourse $action): Response
    {
        $action->unenroll($request->user(), $course, $student);

        return response()->noContent();
    }
}
