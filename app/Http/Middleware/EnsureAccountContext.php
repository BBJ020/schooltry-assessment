<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountContext
{
    public function __construct(
        private readonly EnsureTenant $tenant,
        private readonly EnsurePlatformContext $platform,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $request->user()?->school_id === null
            ? $this->platform->handle($request, $next)
            : $this->tenant->handle($request, $next);
    }
}
