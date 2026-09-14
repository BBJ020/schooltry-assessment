<?php

use App\Http\Middleware\EnsureAccountContext;
use App\Http\Middleware\EnsurePlatformContext;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => EnsureTenant::class,
            'role' => EnsureRole::class,
            'platform' => EnsurePlatformContext::class,
            'account' => EnsureAccountContext::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Unauthenticated.'], 401)
            : null);
        $exceptions->render(fn (AuthorizationException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'This action is unauthorized.'], 403)
            : null);
        $exceptions->render(fn (ModelNotFoundException|NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Resource not found.'], 404)
            : null);
        $exceptions->render(fn (ValidationException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'The given data was invalid.', 'errors' => $e->errors()], 422)
            : null);
        $exceptions->render(function (HttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = match ($e->getStatusCode()) {
                401 => 'Unauthenticated.',
                403 => 'This action is unauthorized.',
                404 => 'Resource not found.',
                429 => 'Too many requests.',
                default => null,
            };

            return $message ? response()->json(['message' => $message], $e->getStatusCode(), $e->getHeaders()) : null;
        });
    })->create();
