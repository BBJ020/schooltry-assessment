<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Actions\Assignments\CreateAssignment;
use App\Actions\Assignments\ReleaseAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assignments\ReleaseAssignmentRequest;
use App\Http\Requests\Assignments\StoreAssignmentRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AssignmentController extends Controller
{
    public function index(Request $request, int $course): AnonymousResourceCollection
    {
        $ownedCourse = Course::query()->visibleTo($request->user())->findOrFail($course);
        Gate::forUser($request->user())->authorize('update', $ownedCourse);

        return AssignmentResource::collection(
            Assignment::query()
                ->visibleTo($request->user())
                ->where('course_id', $ownedCourse->id)
                ->with(['course:id,code,title', 'targets:id,assignment_id,student_id'])
                ->latest()
                ->paginate()
        );
    }

    public function store(StoreAssignmentRequest $request, int $course, CreateAssignment $createAssignment): AssignmentResource
    {
        $ownedCourse = Course::query()->visibleTo($request->user())->findOrFail($course);
        $data = $request->safe()->only([
            'title', 'description', 'maximum_score', 'due_at', 'target_type',
        ]);
        $assignment = $createAssignment->execute(
            $request->user(),
            $ownedCourse,
            $data,
            $request->validated('target_student_ids', []),
        );

        return AssignmentResource::make($assignment->load(['course:id,code,title', 'targets:id,assignment_id,student_id']));
    }

    public function release(ReleaseAssignmentRequest $request, int $assignment, ReleaseAssignment $releaseAssignment): AssignmentResource
    {
        $ownedAssignment = Assignment::query()->visibleTo($request->user())->findOrFail($assignment);

        return AssignmentResource::make(
            $releaseAssignment->execute($request->user(), $ownedAssignment)->load('course:id,code,title')
        );
    }
}
