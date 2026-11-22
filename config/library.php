<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Loan and fine rules
    |--------------------------------------------------------------------------
    | Money is always handled as integer minor units (paise), never floats.
    */

    'currency' => env('LIBRARY_CURRENCY', 'INR'),

    'loan_days' => (int) env('LIBRARY_LOAN_DAYS', 14),

    'max_active_loans' => (int) env('LIBRARY_MAX_ACTIVE_LOANS', 5),

    'fines' => [
        'per_day_minor' => (int) env('LIBRARY_FINE_PER_DAY_MINOR', 200),   // 2.00 per overdue day
        'max_minor' => (int) env('LIBRARY_FINE_MAX_MINOR', 50000),         // 500.00 cap per loan
        'grace_days' => (int) env('LIBRARY_FINE_GRACE_DAYS', 0),
    ],

];
