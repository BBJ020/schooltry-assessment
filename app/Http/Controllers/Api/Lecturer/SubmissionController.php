<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubmissionResource;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SubmissionController extends Controller
{
    public function index(Request $request, int $assignment): AnonymousResourceCollection
    {
        $ownedAssignment = Assignment::query()->visibleTo($request->user())->findOrFail($assignment);
        Gate::forUser($request->user())->authorize('update', $ownedAssignment);

        return SubmissionResource::collection(
            AssignmentSubmission::query()
                ->visibleTo($request->user())
                ->where('assignment_id', $ownedAssignment->id)
                ->with(['student:id,name', 'grade'])
                ->latest('submitted_at')
                ->paginate()
        );
    }
}
