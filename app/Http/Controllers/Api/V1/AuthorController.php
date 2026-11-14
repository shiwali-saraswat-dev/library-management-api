<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Author\StoreAuthorRequest;
use App\Http\Requests\Api\V1\Author\UpdateAuthorRequest;
use App\Http\Resources\Api\V1\AuthorResource;
use App\Models\Author;
use App\Services\AuthorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA; 

class AuthorController extends Controller
{
    // Laravel's service container sees AuthorService in the constructor
    // and automatically creates one for us. We never write "new AuthorService()"
    // ourselves -- that's what makes this swappable/mockable in tests.
    public function __construct(protected AuthorService $authorService) {}

    /**
     * @OA\Get(
     *     path="/authors",
     *     tags={"Authors"},
     *     summary="List authors — search, filter, sort, paginate",
     *     @OA\Parameter(name="filter[genre]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[isbn]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[available]", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="filter[search]", in="query", description="Matches title, isbn, author name, category name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[author]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[category]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[trashed]", in="query", @OA\Schema(type="string", enum={"only","with"})),
     *     @OA\Parameter(name="sort", in="query", description="Prefix with - for descending. Comma-separate for multi-sort.", @OA\Schema(type="string", example="-published_at")),
     *     @OA\Parameter(name="include", in="query", @OA\Schema(type="string", example="author,category")),
     *     @OA\Parameter(name="page[number]", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="page[size]", in="query", @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Book")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=304, description="Not Modified — matches If-None-Match")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Throws a 403 automatically if the logged-in user's policy check fails.
        $this->authorize('viewAny', Author::class);

        // All search/sort/pagination logic lives in the service, not here.
        $authors = $this->authorService->list($request);

        return AuthorResource::collection($authors)->response();
    }

    /**
     * @OA\Post(
     *     path="/authors/store",
     *     tags={"Authors"},
     *     summary="Create an author",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/BookInput")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Book"))),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=409, description="Duplicate request (idempotency key reused)")
     * )
     */
    public function store(StoreAuthorRequest $request): JsonResponse
    {
        $this->authorize('create', Author::class);

        // validated() only contains fields that passed StoreAuthorRequest's rules().
        $author = $this->authorService->create($request->validated());

        // 201 Created is the correct status code for "a new resource was made".
        return (new AuthorResource($author))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Get(
     *     path="/authors/view/{author_id}",
     *     tags={"Authors"},
     *     summary="Get a single book",
     *     @OA\Parameter(name="author_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", @OA\Schema(type="string", example="author,category")),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Book"))),
     *     @OA\Response(response=304, description="Not Modified"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Author $author_id): JsonResponse
    {
        // {author} was already turned into an Author model by route-model-binding
        // before this method even ran (see routes/authors.php). Because the
        // model uses SoftDeletes, a deleted author's id 404s automatically here.
        $this->authorize('view', $author_id);

        $author_id->loadCount('books')->load('books');

        return (new AuthorResource($author_id))->response();
    }

    /**
     * @OA\Put(
     *     path="/authors/update/{author_id}",
     *     tags={"Authors"},
     *     summary="Update an author",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="author_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/BookInput")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Book"))),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateAuthorRequest $request, Author $author_id): JsonResponse
    {
        $this->authorize('update', $author_id);

        $author = $this->authorService->update($author_id, $request->validated());

        return (new AuthorResource($author))->response();
    }

    /**
     * @OA\Delete(
     *     path="/authors/destroy/{author_id}",
     *     tags={"Authors"},
     *     summary="Soft delete an author",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="book_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Deleted (soft)"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(Author $author_id): JsonResponse
    {
        $this->authorize('delete', $author_id);

        $this->authorService->delete($author_id);

        // 204 No Content: deletion succeeded, nothing to send back.
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/authors/trashed",
     *     tags={"Authors"},
     *     summary="List soft-deleted authors",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page[number]", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page[size]", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Book"))))
     * )
     */
    public function trashed(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Author::class);

        $authors = $this->authorService->listTrashed($request);

        return AuthorResource::collection($authors)->response();
    }

    /**
     * @OA\Patch(
     *     path="/authors/restore/{author_id}",
     *     tags={"Authors"},
     *     summary="Restore a soft-deleted author",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="author_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Restored", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Book"))),
     *     @OA\Response(response=404, description="Not found in trash")
     * )
     */
    public function restore(int $author_id): JsonResponse
    {
        // We authorize against Author::class, not an instance, because we
        // deliberately did NOT use route-model-binding here -- default binding
        // would exclude trashed rows and 404 before we could restore anything.
        $this->authorize('restore', Author::class);

        $author = $this->authorService->restore($author_id);

        return (new AuthorResource($author))->response();
    }

    /**
     * @OA\Delete(
     *     path="/authors/force-delete/{book_id}",
     *     tags={"Authors"},
     *     summary="Permanently delete an author (irreversible)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="author_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Permanently deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function forceDelete(int $author_id): JsonResponse
    {
        $this->authorize('forceDelete', Author::class);

        $this->authorService->forceDelete($author_id);

        return response()->json(null, 204);
    }
}
