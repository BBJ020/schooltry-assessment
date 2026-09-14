<?php

namespace App\Actions\Assignments;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeleteAssignment
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(User $actor, Assignment $assignment): void
    {
        Gate::forUser($actor)->authorize('delete', $assignment);
        $filePath = null;

        DB::transaction(function () use ($actor, $assignment, &$filePath): void {
            $locked = Assignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $locked);
            $old = $locked->getAttributes();
            $filePath = $locked->file_path;

            $locked->delete();
            $this->audit->execute($actor, 'assignment.deleted', $locked, $old, null);
        });

        if ($filePath) {
            try {
                Storage::disk((string) config('filesystems.default', 'local'))->delete($filePath);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
