<?php

use App\Http\Middleware\ApiRateLimit;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\RequestId;
use App\Support\ApiError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Runs first in the api group, so rate-limit and idempotency
        // middleware (and logs) can already see the request ID.
        $middleware->api(prepend: [RequestId::class]);

        $middleware->alias([
            'api.rate.limit' => ApiRateLimit::class,
            'idempotency' => IdempotencyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Never redirect API clients to a login page, even without an Accept header.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(
            fn (Throwable $e, Request $request) => $request->is('api/*')
                ? ApiError::from($e, $request)
                : null
        );
    })
    ->create();