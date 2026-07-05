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

class AuthorController extends Controller
{
    // Laravel's service container sees AuthorService in the constructor
    // and automatically creates one for us. We never write "new AuthorService()"
    // ourselves -- that's what makes this swappable/mockable in tests.
    public function __construct(protected AuthorService $authorService) {}

    // GET /api/authors
    // Supports: ?filter[search]=x  ?filter[nationality]=x  ?sort=-name  ?per_page=15  ?page=2
    public function index(Request $request): JsonResponse
    {
        // Throws a 403 automatically if the logged-in user's policy check fails.
        $this->authorize('viewAny', Author::class);

        // All search/sort/pagination logic lives in the service, not here.
        $authors = $this->authorService->list($request);

        return AuthorResource::collection($authors)->response();
    }

    // POST /api/authors/store
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

    // GET /api/authors/view/{author_id}
    public function show(Author $author_id): JsonResponse
    {
        // {author} was already turned into an Author model by route-model-binding
        // before this method even ran (see routes/authors.php). Because the
        // model uses SoftDeletes, a deleted author's id 404s automatically here.
        $this->authorize('view', $author_id);

        $author_id->loadCount('books')->load('books');

        return (new AuthorResource($author_id))->response();
    }

    // PUT/PATCH /api/authors/update/{author_id}
    public function update(UpdateAuthorRequest $request, Author $author_id): JsonResponse
    {
        $this->authorize('update', $author_id);

        $author = $this->authorService->update($author_id, $request->validated());

        return (new AuthorResource($author))->response();
    }

    // DELETE /api/authors/destroy/{author_id}  (soft delete)
    public function destroy(Author $author_id): JsonResponse
    {
        $this->authorize('delete', $author_id);

        $this->authorService->delete($author_id);

        // 204 No Content: deletion succeeded, nothing to send back.
        return response()->json(null, 204);
    }

    // GET /api/authors/trashed
    public function trashed(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Author::class);

        $authors = $this->authorService->listTrashed($request);

        return AuthorResource::collection($authors)->response();
    }

    // PATCH /api/authors/restore/{author_id}/restore
    public function restore(int $author_id): JsonResponse
    {
        // We authorize against Author::class, not an instance, because we
        // deliberately did NOT use route-model-binding here -- default binding
        // would exclude trashed rows and 404 before we could restore anything.
        $this->authorize('restore', Author::class);

        $author = $this->authorService->restore($author_id);

        return (new AuthorResource($author))->response();
    }

    // DELETE /api/authors/force-delete/{author_id}/force  (irreversible)
    public function forceDelete(int $author_id): JsonResponse
    {
        $this->authorize('forceDelete', Author::class);

        $this->authorService->forceDelete($author_id);

        return response()->json(null, 204);
    }
}
