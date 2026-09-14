<?php

namespace App\Actions\Assignments;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UpdateAssignment
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** @param array<string, mixed> $attributes @param list<int>|null $targetStudentIds */
    public function execute(
        User $actor,
        Assignment $assignment,
        array $attributes,
        ?array $targetStudentIds,
        ?UploadedFile $file,
        bool $removeFile,
    ): Assignment {
        Gate::forUser($actor)->authorize('update', $assignment);

        $diskName = (string) config('filesystems.default', 'local');
        $newFilePath = null;
        $oldFilePath = null;

        try {
            $updated = DB::transaction(function () use (
                $actor,
                $assignment,
                $attributes,
                $targetStudentIds,
                $file,
                $removeFile,
                $diskName,
                &$newFilePath,
                &$oldFilePath,
            ): Assignment {
                $locked = Assignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $locked);

                $old = $locked->getAttributes();
                $oldFilePath = $locked->file_path;
                $nextTargetType = (string) ($attributes['target_type'] ?? $locked->target_type);

                if ($nextTargetType === 'selected' && $targetStudentIds !== null) {
                    $validIds = $locked->course->students()->whereKey($targetStudentIds)
                        ->pluck('users.id')->map(fn ($id) => (int) $id)->all();
                    $requestedIds = array_values(array_unique(array_map('intval', $targetStudentIds)));

                    if (count($validIds) !== count($requestedIds)) {
                        throw new AuthorizationException('Every assignment target must be enrolled in this course and school.');
                    }
                }

                $locked->fill(Arr::only($attributes, [
                    'title', 'description', 'maximum_score', 'due_at', 'target_type',
                ]));

                if ($file) {
                    $extension = $file->extension() ?: 'bin';
                    $generatedName = Str::uuid()->toString().'.'.$extension;
                    $newFilePath = Storage::disk($diskName)->putFileAs(
                        "assignments/{$actor->school_id}/{$locked->course_id}/{$locked->id}",
                        $file,
                        $generatedName,
                        'private',
                    );

                    if (! $newFilePath) {
                        throw new RuntimeException('The assignment file could not be stored.');
                    }

                    $originalName = basename($file->getClientOriginalName());
                    $locked->forceFill([
                        'file_path' => $newFilePath,
                        'file_name' => Str::limit(preg_replace('/[^\pL\pN._ -]/u', '_', $originalName) ?: 'attachment', 255, ''),
                        'file_mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                } elseif ($removeFile) {
                    $locked->forceFill([
                        'file_path' => null,
                        'file_name' => null,
                        'file_mime_type' => null,
                        'file_size' => null,
                    ]);
                }

                $locked->save();

                if ($nextTargetType === 'all') {
                    $locked->targets()->delete();
                } elseif ($targetStudentIds !== null) {
                    $locked->targets()->delete();
                    foreach ($targetStudentIds as $studentId) {
                        $locked->targets()->create(['student_id' => (int) $studentId]);
                    }
                }

                $this->audit->execute($actor, 'assignment.updated', $locked, $old, $locked->getAttributes());

                if ($file || ($removeFile && $oldFilePath)) {
                    $this->audit->execute(
                        $actor,
                        $file ? 'assignment.file_replaced' : 'assignment.file_removed',
                        $locked,
                        Arr::only($old, ['file_name', 'file_mime_type', 'file_size']),
                        Arr::only($locked->getAttributes(), ['file_name', 'file_mime_type', 'file_size']),
                    );
                }

                return $locked->load(['course:id,code,title', 'targets:id,assignment_id,student_id'])->loadExists('submissions');
            });
        } catch (Throwable $exception) {
            if ($newFilePath) {
                Storage::disk($diskName)->delete($newFilePath);
            }

            throw $exception;
        }

        if ($oldFilePath && ($newFilePath || $removeFile) && $oldFilePath !== $newFilePath) {
            try {
                Storage::disk($diskName)->delete($oldFilePath);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $updated;
    }
}
