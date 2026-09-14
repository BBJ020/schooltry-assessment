<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlatformContext
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->is_active && $user->school_id === null && $user->hasRole('superadmin'), 403);
        $this->tenant->clear();

        try {
            return $next($request);
        } finally {
            $this->tenant->clear();
        }
    }
}
