<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Actions\Grades\GradeSubmission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Grades\StoreGradeRequest;
use App\Http\Resources\GradeResource;
use App\Models\AssignmentSubmission;

class GradeController extends Controller
{
    public function store(StoreGradeRequest $request, int $submission, GradeSubmission $gradeSubmission): GradeResource
    {
        $ownedSubmission = AssignmentSubmission::query()->visibleTo($request->user())->findOrFail($submission);
        $grade = $gradeSubmission->execute(
            $request->user(),
            $ownedSubmission,
            (float) $request->validated('score'),
            $request->validated('feedback'),
        );

        return GradeResource::make($grade);
    }
}
