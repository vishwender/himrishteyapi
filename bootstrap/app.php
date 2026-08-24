<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'application' => \App\Http\Middleware\ResolveApplication::class,
        ]);
        /*
    |--------------------------------------------------------------------------
    | Middleware priority
    |--------------------------------------------------------------------------
    |
    | Resolve the application database BEFORE Sanctum authentication.
    |
    | Sanctum needs the application connection when it resolves:
    |
    | personal_access_tokens
    |        ↓
    | tokenable
    |        ↓
    | App\Models\Member
    |
    */

        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\ResolveApplication::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
