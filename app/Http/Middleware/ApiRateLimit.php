<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  string  $preset  Name of the preset defined in config/rate_limits.php
     */
    public function handle(
        Request $request,
        Closure $next,
        string $preset = 'api'
    ): Response {
        $config = config("rate_limits.{$preset}") ?? config('rate_limits.api');

        $maxAttempts = max(1, (int) $config['max_attempts']);
        $decaySeconds = max(1, (int) $config['decay_seconds']);

        // Logged-in users -> user id
        // Guests -> IP Address
        // Prefixed with preset name so different presets never share a counter.
        $key = $preset.':'.($request->user()
            ? 'user:'.$request->user()->id
            : 'ip:'.$request->ip());

        // Has exceeded limit?
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Too many requests. Please try again after {$retryAfter} second(s).",
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'errors' => null,
                'meta' => [
                    'request_id' => 'req_'.now()->format('YmdHisv').'_'.Str::random(6),
                    'limit' => $maxAttempts,
                    'remaining' => 0,
                    'retry_after' => $retryAfter,
                    'retry_at' => now()->addSeconds($retryAfter)->toIso8601String(),
                ],
            ], 429)
                ->header('Retry-After', $retryAfter)
                ->header('X-RateLimit-Limit', $maxAttempts)
                ->header('X-RateLimit-Remaining', 0);
        }

        // Increment counter for this request
        RateLimiter::hit($key, $decaySeconds);

        $remaining = RateLimiter::remaining($key, $maxAttempts);

        $response = $next($request);

        return $response
            ->header('X-RateLimit-Limit', $maxAttempts)
            ->header('X-RateLimit-Remaining', $remaining);
    }
}
