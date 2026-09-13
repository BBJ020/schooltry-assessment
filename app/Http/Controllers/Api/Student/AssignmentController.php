<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AssignmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', Assignment::class);

        return AssignmentResource::collection(
            Assignment::query()->visibleTo($request->user())->with('course:id,code,title')->latest()->paginate()
        );
    }

    public function show(Request $request, int $assignment): AssignmentResource
    {
        $visibleAssignment = Assignment::query()->visibleTo($request->user())
            ->with('course:id,code,title')->findOrFail($assignment);
        Gate::forUser($request->user())->authorize('view', $visibleAssignment);

        return AssignmentResource::make($visibleAssignment);
    }
}
