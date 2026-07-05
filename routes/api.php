<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AuthorController;
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

        // -------------------------------------------------------------------------
        // Author Routes
        // -------------------------------------------------------------------------
        /**
         * ORDER MATTERS: /authors/trashed must be declared BEFORE /authors/{author}.
         * Laravel matches routes top-to-bottom — if {author} came first, a request
         * to /authors/trashed would match {author} with the literal string "trashed"
         * and 404/500 trying to look up an author with id "trashed" instead of ever
         * reaching the trashed() method.
         */
        Route::prefix('authors')->name('authors.')
            ->middleware('auth:sanctum')
            ->group(function () {
                Route::get('/', [AuthorController::class, 'index'])->name('index');
                Route::post('/store', [AuthorController::class, 'store'])->name('store');

                Route::get('/trashed', [AuthorController::class, 'trashed'])->name('trashed');
                Route::patch('/restore/{author_id}', [AuthorController::class, 'restore'])
                    ->whereNumber('author_id')
                    ->name('restore');
                Route::delete('/force-delete/{author_id}', [AuthorController::class, 'forceDelete'])
                    ->whereNumber('author_id')
                    ->name('force_delete');

                // These use implicit route-model-binding: Laravel resolves {author} into
                // an App\Models\Author instance automatically before the controller
                // method runs, 404-ing early if no matching (non-trashed) row exists.
                Route::get('/view/{author_id}', [AuthorController::class, 'show'])->name('show');
                Route::put('/update/{author_id}', [AuthorController::class, 'update'])->name('update');
                Route::patch('/update/{author_id}', [AuthorController::class, 'update']);
                Route::delete('/destroy/{author_id}', [AuthorController::class, 'destroy'])->name('destroy');
            }
            );

    }
    );
