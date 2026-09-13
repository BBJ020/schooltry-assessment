<?php

namespace App\Database\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

class AppendOnlyBuilder extends Builder
{
    public function update(array $values)
    {
        throw new LogicException('Audit logs are append-only.');
    }

    public function delete()
    {
        throw new LogicException('Audit logs are append-only.');
    }

    public function forceDelete()
    {
        throw new LogicException('Audit logs are append-only.');
    }
}
