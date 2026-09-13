<?php

namespace App\Actions\Submissions;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SubmitAssignment
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(User $student, Assignment $assignment, array $attributes): AssignmentSubmission
    {
        Gate::forUser($student)->authorize('submit', [AssignmentSubmission::class, $assignment]);

        $file = $attributes['file'] ?? null;
        $storedPath = null;
        $storedMetadata = [];

        if ($file instanceof UploadedFile) {
            $storedPath = Storage::disk('s3')->putFile(
                "schools/{$student->school_id}/assignments/{$assignment->id}",
                $file,
                'private',
            );

            if (! $storedPath) {
                throw new RuntimeException('The submission file could not be stored.');
            }

            $originalName = basename($file->getClientOriginalName());
            $storedMetadata = [
                'file_path' => $storedPath,
                'original_filename' => Str::limit(preg_replace('/[^\pL\pN._ -]/u', '_', $originalName) ?: 'upload', 255, ''),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ];
        }

        $oldFilePath = null;

        try {
            $submission = DB::transaction(function () use (
                $student,
                $assignment,
                $attributes,
                $storedMetadata,
                &$oldFilePath,
            ): AssignmentSubmission {
                $submission = AssignmentSubmission::query()
                    ->where('assignment_id', $assignment->id)->where('student_id', $student->id)->first();
                $old = $submission?->getAttributes();
                $oldFilePath = $submission?->file_path;

                if ($submission) {
                    Gate::forUser($student)->authorize('update', $submission);
                }

                $submission ??= new AssignmentSubmission;
                $submission->fill(Arr::only($attributes, ['submission_text']));
                $submission->fill($storedMetadata);
                $submission->forceFill([
                    'school_id' => $student->school_id,
                    'assignment_id' => $assignment->id,
                    'student_id' => $student->id,
                    'submitted_at' => now(),
                ])->save();
                $this->audit->execute($student, $old ? 'submission.updated' : 'submission.created', $submission, $old, $submission->getAttributes());

                return $submission;
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('s3')->delete($storedPath);
            }

            throw $exception;
        }

        if ($storedPath && $oldFilePath && $oldFilePath !== $storedPath) {
            Storage::disk('s3')->delete($oldFilePath);
        }

        return $submission;
    }
}
