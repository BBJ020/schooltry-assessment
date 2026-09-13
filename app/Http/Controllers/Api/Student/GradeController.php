<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\GradeResource;
use App\Models\Assignment;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GradeController extends Controller
{
    public function show(Request $request, int $assignment): GradeResource
    {
        $visibleAssignment = Assignment::query()->visibleTo($request->user())->findOrFail($assignment);
        Gate::forUser($request->user())->authorize('view', $visibleAssignment);

        $grade = Grade::query()->visibleTo($request->user())
            ->where('assignment_id', $visibleAssignment->id)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();
        Gate::forUser($request->user())->authorize('view', $grade);

        return GradeResource::make($grade);
    }
}
