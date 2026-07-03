<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Strict brute-force protection for login, keyed by email + IP.
        // Numbers pulled from config/rate_limits.php so there's one source of truth.
        RateLimiter::for('login', function (Request $request) {
            $config = config('rate_limits.login_strict');
 
            $email = Str::lower((string) $request->input('email'));
            $key = $email . '|' . $request->ip();
 
            return Limit::perMinute($config['max_attempts'])
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again later.',
                        'error_code' => 'RATE_LIMIT_EXCEEDED',
                        'errors' => null,
                        'meta' => [
                            'retry_after' => $headers['Retry-After'] ?? null,
                        ],
                    ], 429, $headers);
                });
        });
    }
}