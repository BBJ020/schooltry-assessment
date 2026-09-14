<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Actions\Assignments\CreateAssignment;
use App\Actions\Assignments\DeleteAssignment;
use App\Actions\Assignments\ReleaseAssignment;
use App\Actions\Assignments\UpdateAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assignments\DeleteAssignmentRequest;
use App\Http\Requests\Assignments\ReleaseAssignmentRequest;
use App\Http\Requests\Assignments\StoreAssignmentRequest;
use App\Http\Requests\Assignments\UpdateAssignmentRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
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
                ->withExists('submissions')
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
            $request->file('file'),
        );

        return AssignmentResource::make(
            $assignment->load(['course:id,code,title', 'targets:id,assignment_id,student_id'])->loadExists('submissions')
        );
    }

    public function update(UpdateAssignmentRequest $request, int $assignment, UpdateAssignment $updateAssignment): AssignmentResource
    {
        $ownedAssignment = Assignment::query()->visibleTo($request->user())->findOrFail($assignment);
        $targetStudentIds = $request->has('target_student_ids')
            ? $request->validated('target_student_ids', [])
            : null;

        return AssignmentResource::make($updateAssignment->execute(
            $request->user(),
            $ownedAssignment,
            $request->safe()->only(['title', 'description', 'maximum_score', 'due_at', 'target_type']),
            $targetStudentIds,
            $request->file('file'),
            $request->boolean('remove_file'),
        ));
    }

    public function destroy(DeleteAssignmentRequest $request, int $assignment, DeleteAssignment $deleteAssignment): Response
    {
        $ownedAssignment = Assignment::query()->visibleTo($request->user())->findOrFail($assignment);
        $deleteAssignment->execute($request->user(), $ownedAssignment);

        return response()->noContent();
    }

    public function release(ReleaseAssignmentRequest $request, int $assignment, ReleaseAssignment $releaseAssignment): AssignmentResource
    {
        $ownedAssignment = Assignment::query()->visibleTo($request->user())->findOrFail($assignment);

        return AssignmentResource::make(
            $releaseAssignment->execute($request->user(), $ownedAssignment)
                ->load('course:id,code,title')->loadExists('submissions')
        );
    }
}
