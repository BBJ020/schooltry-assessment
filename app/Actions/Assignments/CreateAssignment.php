<?php

namespace App\Actions\Assignments;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreateAssignment
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** @param array<string, mixed> $attributes @param list<int> $targetStudentIds */
    public function execute(
        User $actor,
        Course $course,
        array $attributes,
        array $targetStudentIds = [],
        ?UploadedFile $file = null,
    ): Assignment {
        Gate::forUser($actor)->authorize('create', Assignment::class);
        Gate::forUser($actor)->authorize('update', $course);

        $diskName = (string) config('filesystems.default', 'local');
        $storedPath = null;

        try {
            return DB::transaction(function () use ($actor, $course, $attributes, $targetStudentIds, $file, $diskName, &$storedPath): Assignment {
                $assignment = new Assignment($attributes);
                $assignment->forceFill([
                    'school_id' => $actor->school_id,
                    'course_id' => $course->id,
                    'created_by' => $actor->id,
                    'is_released' => false,
                    'released_at' => null,
                    'released_by' => null,
                ])->save();

                if ($assignment->target_type === 'selected') {
                    $validIds = $course->students()->whereKey($targetStudentIds)->pluck('users.id')->map(fn ($id) => (int) $id)->all();
                    $requestedIds = array_values(array_unique(array_map('intval', $targetStudentIds)));

                    if (count($validIds) !== count($requestedIds)) {
                        throw new AuthorizationException('Every assignment target must be enrolled in this course and school.');
                    }

                    foreach ($validIds as $studentId) {
                        $assignment->targets()->create(['student_id' => $studentId]);
                    }
                }

                if ($file) {
                    $extension = $file->extension() ?: 'bin';
                    $generatedName = Str::uuid()->toString().'.'.$extension;
                    $storedPath = Storage::disk($diskName)->putFileAs(
                        "assignments/{$actor->school_id}/{$course->id}/{$assignment->id}",
                        $file,
                        $generatedName,
                        'private',
                    );

                    if (! $storedPath) {
                        throw new RuntimeException('The assignment file could not be stored.');
                    }

                    $originalName = basename($file->getClientOriginalName());
                    $assignment->forceFill([
                        'file_path' => $storedPath,
                        'file_name' => Str::limit(preg_replace('/[^\pL\pN._ -]/u', '_', $originalName) ?: 'attachment', 255, ''),
                        'file_mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ])->save();
                }

                $this->audit->execute($actor, 'assignment.created', $assignment, null, $assignment->getAttributes());

                return $assignment;
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk($diskName)->delete($storedPath);
            }

            throw $exception;
        }
    }
}
