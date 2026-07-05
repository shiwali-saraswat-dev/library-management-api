<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Presets
    |--------------------------------------------------------------------------
    |
    | Single source of truth for ALL rate limiting - both the custom
    | ApiRateLimit middleware (keyed by user/IP) and the named
    | RateLimiter::for() limiters in AppServiceProvider (keyed by
    | email+IP for login). Change numbers here only.
    |
    | Note: named Laravel limiters (Limit::perMinute) only support a
    | 60-second window, so decay_seconds for 'login' should stay 60
    | unless you switch that limiter to a manual Limit::perMinutes().
    |
    */

    'api' => [
        'max_attempts' => 60,
        'decay_seconds' => 60,
    ],

    // Broader guard: max attempts per IP address for the login route,
    // regardless of which email is being attempted. Catches IP-hopping
    // or someone trying many different accounts from one machine.
    'login' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],

    // Tighter guard: max attempts against one specific account (by email),
    // combined with IP. This is the actual brute-force stopper.
    'login_strict' => [
        'max_attempts' => 2,
        'decay_seconds' => 60,
    ],

    'register' => [
        'max_attempts' => 5,
        'decay_seconds' => 300,
    ],

];
