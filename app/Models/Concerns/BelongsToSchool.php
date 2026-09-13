<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $builder): void {
            $schoolId = app(TenantContext::class)->schoolId();

            if ($schoolId !== null) {
                $builder->where($builder->qualifyColumn('school_id'), $schoolId);
            }
        });

        static::saving(function (Model $model): void {
            $schoolId = app(TenantContext::class)->schoolId();

            if ($schoolId === null) {
                return;
            }

            if ($model->getAttribute('school_id') === null) {
                $model->setAttribute('school_id', $schoolId);
            }

            if ((int) $model->getAttribute('school_id') !== $schoolId) {
                throw new LogicException('A tenant-owned record cannot be written outside the active school.');
            }

            foreach ($model->tenantParentKeys() as $foreignKey => $relatedModel) {
                $relatedId = $model->getAttribute($foreignKey);

                if ($relatedId !== null && ! $relatedModel::query()->whereKey($relatedId)->exists()) {
                    throw new LogicException("The {$foreignKey} record does not belong to the active school.");
                }
            }
        });
    }

    /** @return array<string, class-string<Model>> */
    protected function tenantParentKeys(): array
    {
        return [];
    }
}
