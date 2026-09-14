<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RecordAuditLog
{
    /** @param array<string, mixed>|null $oldValues @param array<string, mixed>|null $newValues */
    public function execute(User $actor, string $action, Model $auditable, ?array $oldValues, ?array $newValues, ?int $schoolId = null): AuditLog
    {
        $request = app()->bound('request') ? request() : null;

        $log = new AuditLog([
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);

        $log->forceFill([
            'school_id' => $schoolId ?? $actor->school_id,
            'actor_id' => $actor->id,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
        ])->save();

        return $log;
    }
}
