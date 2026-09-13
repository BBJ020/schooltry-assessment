<?php

namespace App\Http\Controllers\Api\Student;

use App\Actions\Submissions\SubmitAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submissions\StoreSubmissionRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SubmissionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', AssignmentSubmission::class);

        return SubmissionResource::collection(
            AssignmentSubmission::query()->visibleTo($request->user())->latest('submitted_at')->paginate()
        );
    }

    public function show(Request $request, int $submission): SubmissionResource
    {
        $ownSubmission = AssignmentSubmission::query()->visibleTo($request->user())->findOrFail($submission);
        Gate::forUser($request->user())->authorize('view', $ownSubmission);

        return SubmissionResource::make($ownSubmission);
    }

    public function store(
        StoreSubmissionRequest $request,
        int $assignment,
        SubmitAssignment $submitAssignment,
    ): SubmissionResource {
        $visibleAssignment = Assignment::query()->visibleTo($request->user())->findOrFail($assignment);
        $submission = $submitAssignment->execute($request->user(), $visibleAssignment, [
            'submission_text' => $request->validated('submission_text'),
            'file' => $request->file('file'),
        ]);

        return SubmissionResource::make($submission);
    }
}
