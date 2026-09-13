<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenant
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 401);
        abort_unless($user->is_active && $user->school_id, 403, 'An active school account is required.');
        abort_unless($user->school()->where('is_active', true)->exists(), 403, 'The school is inactive.');

        $this->tenant->setFor($user);

        try {
            return $next($request);
        } finally {
            $this->tenant->clear();
        }
    }
}
