<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Apply only to mutating requests
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (blank($key)) {
            return response()->json([
                'message' => 'Idempotency-Key header is required.',
            ], 400);
        }

        $cacheKey = 'idempotency:'.$key;

        if (Cache::has($cacheKey)) {
            return response()->json([
                'message' => 'Duplicate request.',
            ], 409);
        }

        Cache::put($cacheKey, true, now()->addMinutes(10));

        return $next($request);
    }
}
