<?php

namespace App\Actions\Assignments;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReleaseAssignment
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(User $actor, Assignment $assignment): Assignment
    {
        Gate::forUser($actor)->authorize('release', $assignment);

        return DB::transaction(function () use ($actor, $assignment): Assignment {
            $old = $assignment->getAttributes();
            $assignment->forceFill(['is_released' => true, 'released_at' => now(), 'released_by' => $actor->id])->save();
            $this->audit->execute($actor, 'assignment.released', $assignment, $old, $assignment->getAttributes());

            return $assignment;
        });
    }
}
