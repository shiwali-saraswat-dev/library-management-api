<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AuthorController;
use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MemberController;
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

        // =========================================================================
        // Category routes
        // =========================================================================

        Route::prefix('categories')->name('categories.')
            ->middleware('auth:sanctum')
            ->group(function (): void {

                // -----------------------------------------------------------------
                // Public routes — no auth required
                // -----------------------------------------------------------------

                Route::get('/', [CategoryController::class, 'index'])
                    ->name('index')
                    ->middleware('throttle:api');

                Route::get('/view/{category_id}', [CategoryController::class, 'show'])
                    ->name('show')
                    ->whereNumber('category_id')
                    ->middleware('throttle:api');

                // -----------------------------------------------------------------
                // Protected routes — auth required; role checks handled by Policy
                // -----------------------------------------------------------------

                Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {

                    // Create — idempotency middleware prevents duplicate on retry
                    Route::post('/store', [CategoryController::class, 'store'])
                        ->name('store')
                        ->middleware('idempotency');

                    // Update
                    Route::put('/update/{category_id}', [CategoryController::class, 'update'])
                        ->name('update')
                        ->whereNumber('category_id');

                    // Soft delete
                    Route::delete('/destroy/{category_id}', [CategoryController::class, 'destroy'])
                        ->name('destroy')
                        ->whereNumber('category_id');

                    // Restore — POST because it's a state change, not a resource creation
                    // FIX 25: Must be defined BEFORE /{category_id} to avoid ambiguity.
                    //         Laravel matches routes top-to-bottom — /5/restore must not
                    //         be swallowed by /{category_id} as "category_id=5/restore".
                    //         Using a nested prefix solves this cleanly.
                    Route::patch('/restore/{category_id}', [CategoryController::class, 'restore'])
                        ->name('restore')
                        ->whereNumber('category_id');

                    // Force delete — permanent, irreversible
                    Route::delete('/force-delete/{category_id}', [CategoryController::class, 'forceDelete'])
                        ->name('force-delete')
                        ->whereNumber('category_id');

                    // Category's books — nested resource, read-only from category side
                    // Route::get('/{category_id}/books', [\App\Http\Controllers\Api\V1\BookController::class, 'byCategory'])
                    //     ->name('books.index')
                    //     ->whereNumber('category_id');
                });
            }
            );

        // =========================================================================
        // Book routes
        // =========================================================================

        Route::prefix('books')->name('books.')
            ->group(function (): void {

                // -----------------------------------------------------------------
                // Public routes — catalog reads, no auth required
                // -----------------------------------------------------------------

                Route::get('/', [BookController::class, 'index'])
                    ->name('index')
                    ->middleware('throttle:api');

                // Must come before /view/{book_id} for the same reason trashed()
                // must precede {author} in the Authors group — Laravel matches
                // top-to-bottom, and {book_id} would swallow the literal "trashed".
                // (Trashed listing is arguably a protected/librarian-only action —
                // moved into the auth group below if so; kept here only if reads
                // should stay public.)

                Route::get('/view/{book_id}', [BookController::class, 'show'])
                    ->name('show')
                    ->whereNumber('book_id')
                    ->middleware('throttle:api');

                // -----------------------------------------------------------------
                // Protected routes — auth required; role checks via Policy
                // -----------------------------------------------------------------

                Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {

                    Route::get('/trashed', [BookController::class, 'trashed'])->name('trashed');

                    Route::post('/store', [BookController::class, 'store'])
                        ->name('store')
                        ->middleware('idempotency');

                    Route::put('/update/{book_id}', [BookController::class, 'update'])
                        ->name('update')
                        ->whereNumber('book_id');
                    Route::patch('/update/{book_id}', [BookController::class, 'update']);

                    Route::delete('/destroy/{book_id}', [BookController::class, 'destroy'])
                        ->name('destroy')
                        ->whereNumber('book_id');

                    // Must be declared before nothing here conflicts — /restore/{book_id}
                    // and /force-delete/{book_id} are their own literal-first segments,
                    // so no ordering trap like the /trashed one above.
                    Route::patch('/restore/{book_id}', [BookController::class, 'restore'])
                        ->name('restore')
                        ->whereNumber('book_id');

                    Route::delete('/force-delete/{book_id}', [BookController::class, 'forceDelete'])
                        ->name('force-delete')
                        ->whereNumber('book_id');
                });
            }
            );

        
        
        // =========================================================================
        // Member routes
        // =========================================================================

        Route::prefix('members')->name('members.')->middleware('auth:sanctum')->group(function (): void {
            Route::get('/', [MemberController::class, 'index'])
                ->name('index')
                ->middleware('throttle:api');

            Route::post('/store', [MemberController::class, 'store'])
                ->name('store')
                ->middleware('throttle:api');

            Route::get('/view/{member_id}', [MemberController::class, 'show'])
                ->name('show')
                ->whereNumber('member_id')
                ->middleware('throttle:api');

            Route::put('/update/{member_id}', [MemberController::class, 'update'])
                ->name('update')
                ->whereNumber('member_id')
                ->middleware('throttle:api');

            Route::delete('/destroy/{member_id}', [MemberController::class, 'destroy'])
                ->name('destroy')
                ->whereNumber('member_id')
                ->middleware('throttle:api');

            Route::patch('/restore/{member_id}', [MemberController::class, 'restore'])
                ->name('restore')
                ->whereNumber('member_id')
                ->middleware('throttle:api');

            Route::delete('/force-delete/{member_id}', [MemberController::class, 'forceDelete'])
                ->name('force-delete')
                ->whereNumber('member_id')
                ->middleware('throttle:api');
        });
          

    }
    );
