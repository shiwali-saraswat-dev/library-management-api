<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimit
{
    /**
     * @param  string  $preset  Name of the preset defined in config/rate_limits.php
     */
    public function handle(Request $request, Closure $next, string $preset = 'api'): Response
    {
        $config = config("rate_limits.{$preset}") ?? config('rate_limits.api');

        $maxAttempts = max(1, (int) $config['max_attempts']);
        $decaySeconds = max(1, (int) $config['decay_seconds']);

        // Explicit 'sanctum' guard: works whether or not auth:sanctum ran earlier.
        // Prefixed with preset so different presets never share a counter.
        $user = $request->user('sanctum');
        $key = $preset.':'.($user ? 'user:'.$user->id : 'ip:'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            return ApiError::respond(
                $request,
                status: 429,
                message: "Too many requests. Please try again after {$retryAfter} second(s).",
                code: 'RATE_LIMIT_EXCEEDED',
                meta: [
                    'limit' => $maxAttempts,
                    'remaining' => 0,
                    'retry_after' => $retryAfter,
                    'retry_at' => now()->addSeconds($retryAfter)->toIso8601String(),
                ],
                headers: [
                    'Retry-After' => $retryAfter,
                    'X-RateLimit-Limit' => $maxAttempts,
                    'X-RateLimit-Remaining' => 0,
                ],
            );
        }

        RateLimiter::hit($key, $decaySeconds);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($key, $maxAttempts));

        return $response;
    }
}