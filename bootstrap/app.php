<?php

use App\Http\Middleware\ApiRateLimit;
use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'api.rate.limit' => ApiRateLimit::class,
            'idempotency' => IdempotencyMiddleware::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'error_code' => 'VALIDATION_ERROR',
                    'errors' => $e->errors(),
                    'meta' => [
                        'request_id' => 'req_'.Str::random(8),
                    ],
                ], 422);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'The requested resource could not be found.',
                    'error_code' => 'RESOURCE_NOT_FOUND',
                    'errors' => null,
                    'meta' => [
                        'request_id' => 'req_'.Str::random(8),
                    ],
                ], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'error_code' => 'UNAUTHENTICATED',
                    'errors' => null,
                    'meta' => [
                        'request_id' => 'req_'.Str::random(8),
                    ],
                ], 401);
            }
        });

    })
    ->create();
