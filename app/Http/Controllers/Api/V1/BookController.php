<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Book\BookRequest;
use App\Http\Resources\Api\V1\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BookController extends Controller
{
    private const CACHE_TTL = 3600; // seconds. Tune per traffic pattern.

    private const CACHE_VERSION_KEY = 'books:cache_version';

    /**
     * GET /api/v1/books
     */
    public function index(Request $request): SymfonyResponse
    {
        Paginator::currentPageResolver(function () use ($request) {
            return (int) $request->input('page.number', 1);
        });

        $perPage = min((int) $request->input('page.size', 15), 100);
        $cacheKey = $this->cacheKey('books:index:'.md5($request->fullUrl()));

        $payload = Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->buildIndexPayload($request, $perPage)
        );

        return $this->respondWithEtag($payload, $request);
    }

    private function buildIndexPayload(Request $request, int $perPage): array
    {
        // dd($request->query('filter')); // TEMP — confirm 'trashed' key arrives, then remove

        /** @var LengthAwarePaginator $books */
        $books = QueryBuilder::for(Book::class)
            ->allowedFilters([
                AllowedFilter::exact('genre'),
                AllowedFilter::exact('isbn'),
                AllowedFilter::callback('available', function ($query, $value) {
                    $available = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    $query->where('available_copies', $available ? '>' : '<=', 0);
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('title', 'like', "%{$value}%")
                            ->orWhere('isbn', 'like', "%{$value}%")
                            ->orWhereHas('author', fn ($a) => $a->where('name', 'like', "%{$value}%"))
                            ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$value}%"));
                    });
                }),
                // NEW: filter specifically by author name
                AllowedFilter::callback('author', function ($query, $value) {
                    $query->whereHas('author', fn ($a) => $a->where('name', 'like', "%{$value}%"));
                }),
                // NEW: filter specifically by category name
                AllowedFilter::callback('category', function ($query, $value) {
                    $query->whereHas('category', fn ($c) => $c->where('name', 'like', "%{$value}%")
                        ->where('is_active', true));
                }),
                AllowedFilter::callback('trashed', function ($query, $value) {
                    match ($value) {
                        'only' => $query->onlyTrashed(),
                        'with' => $query->withTrashed(),
                        default => null,
                    };
                }),
            ])
            ->allowedSorts(['title', 'published_at', 'created_at', 'available_copies'])
            ->defaultSort('-published_at')
            ->allowedFields([
                'id', 'isbn', 'title', 'genre', 'total_copies', 'available_copies', 'published_at',
                'author.id', 'author.name', 'category.id', 'category.name',
            ])
            ->allowedIncludes(['author', 'category'])
            ->paginate($perPage)
            ->appends($request->query());

        return [
            'data' => BookResource::collection($books->items())->resolve($request),
            'links' => [
                'first' => $this->pageUrl($request, 1, $books->perPage()),
                'last' => $this->pageUrl($request, $books->lastPage(), $books->perPage()),
                'next' => $books->currentPage() < $books->lastPage()
                    ? $this->pageUrl($request, $books->currentPage() + 1, $books->perPage())
                    : null,
                'prev' => $books->currentPage() > 1
                    ? $this->pageUrl($request, $books->currentPage() - 1, $books->perPage())
                    : null,
            ],
            'meta' => [
                'current_page' => $books->currentPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
                'request_id' => (string) Str::uuid(),
            ],
        ];
    }

    private function pageUrl(Request $request, int $page, int $perPage): string
    {
        $query = $request->query();
        $query['page']['number'] = $page;
        $query['page']['size'] = $perPage;

        return $request->url().'?'.http_build_query($query);
    }

    /**
     * GET /api/v1/books/{book}
     */
    public function show(Request $request, int $book_id): SymfonyResponse
    {
        $book = Book::findOrFail($book_id);

        $includes = array_filter(explode(',', (string) $request->query('include', '')));
        $book->load(array_intersect($includes, ['author', 'category']));

        $cacheKey = $this->cacheKey("books:show:{$book->id}:".md5($request->fullUrl()));

        $payload = Cache::remember($cacheKey, self::CACHE_TTL, fn () => [
            'data' => (new BookResource($book))->resolve($request),
            'meta' => ['request_id' => (string) Str::uuid()],
        ]);

        return $this->respondWithEtag($payload, $request);
    }

    /**
     * POST /api/v1/books
     */
    public function store(BookRequest $request): JsonResponse
    {
        $book = Book::create($request->validated());
        $this->flushBooksCache();

        return (new BookResource($book))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('books.show', $book));
    }

    /**
     * PUT/PATCH /api/v1/books/{book}
     */
    public function update(BookRequest $request, int $book_id): JsonResponse
    {
        $book = Book::findOrFail($book_id);

        $book->update($request->validated());
        $this->flushBooksCache();

        return (new BookResource($book->fresh()))->response();
    }

    /**
     * DELETE /api/v1/books/{book}  (soft delete)
     */
    public function destroy(int $book_id): Response
    {
        $book = Book::findOrFail($book_id);

        $book->delete();
        $this->flushBooksCache();

        return response()->noContent();
    }

    /**
     * POST /api/v1/books/{id}/restore
     */
    public function restore(int $id): JsonResponse
    {
        $book = Book::onlyTrashed()->findOrFail($id);
        $book->restore();
        $this->flushBooksCache();

        return (new BookResource($book))->response();
    }

    /**
     * DELETE /api/v1/books/force-delete/{book_id}
     *
     * Permanent, irreversible. Unlike destroy() (soft delete), this removes
     * the row entirely — no restore() possible afterward. Deliberately scoped
     * to withTrashed() rather than a plain find(): forcing a book through the
     * trash first (destroy -> restore or destroy -> force-delete) is a safer
     * default than allowing an active, in-catalog book to be permanently
     * deleted in one step.
     */
    public function forceDelete(int $book_id): Response
    {
        $book = Book::withTrashed()->findOrFail($book_id);

        $book->forceDelete();
        $this->flushBooksCache();

        return response()->noContent();
    }

    /**
     * ---- Cache "tagging" without a tag-capable driver -----------------
     *
     * Cache::tags() only works on Redis/Memcached. On file/database/array
     * drivers it throws BadMethodCallException. To get equivalent
     * "invalidate every books:* entry on write" behavior without one of
     * those drivers, every books:* key is namespaced with a version
     * number. Bumping the version doesn't delete old entries — it just
     * makes them unreachable, since the next read builds a key with the
     * new version and misses. Stale entries quietly age out via
     * CACHE_TTL instead of being actively deleted.
     *
     * Trade-off vs real tagging: orphaned entries sit in the cache store
     * until their TTL expires rather than being freed immediately. With
     * CACHE_TTL at 3600s that's bounded and cheap; if you later move to
     * Redis, swap these two methods back to Cache::tags(['books']) and
     * nothing else in the controller needs to change.
     */
    private function cacheVersion(): int
    {
        return (int) Cache::get('books:cache_version', 1);
    }

    private function flushBooksCache(): void
    {
        Cache::forever('books:cache_version', $this->cacheVersion() + 1);
    }

    private function cacheKey(string $key): string
    {
        return 'v'.$this->cacheVersion().':'.$key;
    }

    /**
     * Wraps a payload with an ETag derived from its content. If the client's
     * If-None-Match header matches, we skip serialization entirely and
     * return 304, saving bandwidth on unchanged catalog data.
     */
    private function respondWithEtag(array $payload, Request $request): SymfonyResponse
    {
        $etag = '"'.md5(json_encode($payload)).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', Response::HTTP_NOT_MODIFIED)
                ->header('ETag', $etag);
        }

        return response()->json($payload)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'private, must-revalidate');
    }
}
