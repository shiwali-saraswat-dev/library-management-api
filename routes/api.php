<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BooksController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::prefix('v1')
    ->middleware('api.rate.limit:api') // Layer 1: general guard, 60/60s, applies to whole group
    ->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('register', [AuthController::class, 'register'])
                ->middleware('api.rate.limit:register');
 
            // Layer 1 (api.rate.limit:api) already applied via the group above.
            // Layer 2 + 3 stacked here, in the order they should run:
            Route::post('login', [AuthController::class, 'login'])
                ->middleware([
                    'api.rate.limit:login', // Layer 2: 5/60s, IP-wide
                    'throttle:login',       // Layer 3: 2/60s, email+IP (login_strict preset)
                ]);
 
            Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');
            Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        });
    }
);