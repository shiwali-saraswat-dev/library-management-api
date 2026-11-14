<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Category\ListCategoryRequest;
use App\Http\Requests\Api\V1\Category\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Category\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\CategoryCollection;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA; 

/**
 * @group Categories
 *
 * Manage book categories. All mutating endpoints require admin or librarian role.
 * Soft-delete, restore, and force-delete require admin role.
 */
class CategoryController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    /**
     * @OA\Get(
     *     path="/categories",
     *     tags={"Categories"},
     *     summary="List categories — search, filter, sort, paginate",
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
    public function index(ListCategoryRequest $request): CategoryCollection
    {
        $categories = $this->categoryService->list($request->validated());

        return new CategoryCollection($categories);
    }

    /**
     * @OA\Get(
     *     path="/categories/view/{category_id}",
     *     tags={"Categories"},
     *     summary="Get a single category",
     *     @OA\Parameter(name="category_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", @OA\Schema(type="string", example="author,category")),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Book"))),
     *     @OA\Response(response=304, description="Not Modified"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, int $category_id): JsonResponse
    {
        // FIX 1: Cannot use {category} route model binding for show() because
        // default binding excludes soft-deleted records (whereNull('deleted_at')).
        // Admins should be able to view trashed records — so we resolve manually
        // with withTrashed() and let the Policy decide who can view it.
        $category = Category::withTrashed()->findOrFail($category_id);

        $this->authorize('view', $category);

        // FIX 2: Role check was 'owner' — there is no 'owner' role in this system.
        // Correct roles are: admin, librarian, member.
        if (in_array($request->user()?->role, ['admin', 'librarian'], true)) {
            $category->load(['createdBy', 'updatedBy']);
        }

        $category->loadCount('books');

        // FIX 3: Was returning (new CategoryResource($category))->response() which
        // bypasses ApiResponseTrait and returns a different envelope shape than
        // every other endpoint. Use $this->success() for consistency.
        return $this->success(
            data: new CategoryResource($category),
            // message: 'Category retrieved successfully.',
        );
    }

    /**
     * @OA\Post(
     *     path="/categories/store",
     *     tags={"Categories"},
     *     summary="Create a category",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/BookInput")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Book"))),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=409, description="Duplicate request (idempotency key reused)")
     * )
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create(
            data: $request->validated(),
            userId: $request->user()->id,
        );

        return $this->created(
            data: new CategoryResource($category),
            message: 'Category created successfully.',
        );
    }

    /**
     * @OA\Put(
     *     path="/categories/update/{book_id}",
     *     tags={"Categories"},
     *     summary="Update a category",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="category_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/BookInput")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Book"))),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateCategoryRequest $request, int $category_id): JsonResponse
    {
        // FIX 4: Changed from Category $category (route model binding) to int $category_id.
        // Route binding must also be changed in routes/api.php to match {category_id}.
        // We resolve the model here explicitly so naming is consistent and clear.
        $category = Category::findOrFail($category_id);

        // FIX 5: Authorization must happen BEFORE the service call.
        // UpdateCategoryRequest::authorize() already checks can('update', $category)
        // but only via the FormRequest's own route binding. Since we switched to
        // int param, we must authorize explicitly here as a safety net.
        $this->authorize('update', $category);

        $updated = $this->categoryService->update(
            category: $category,
            data: $request->validated(),
            userId: $request->user()->id,
        );

        return $this->success(
            data: new CategoryResource($updated),
            message: 'Category updated successfully.',
        );
    }

    /**
     * @OA\Delete(
     *     path="/categories/destroy/{book_id}",
     *     tags={"Categories"},
     *     summary="Soft delete a category",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="category_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Deleted (soft)"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(int $category_id): JsonResponse
    {
        $category = Category::findOrFail($category_id);

        $this->authorize('delete', $category);

        // Load books_count so CategoryHasBooksException has the count for its message
        $category->loadCount('books');

        $this->categoryService->softDelete($category);

        // return $this->noContent('Category moved to trash. It can be restored by an admin.');

        return $this->ok(data: null, message: 'Category moved to trash. It can be restored by an admin.');
    }

    /**
     * @OA\Patch(
     *     path="/categories/restore/{book_id}",
     *     tags={"Categories"},
     *     summary="Restore a soft-deleted category",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="category_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Restored", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Book"))),
     *     @OA\Response(response=404, description="Not found in trash")
     * )
     */
    public function restore(int $category_id): JsonResponse
    {
        // Must use withTrashed() — default binding hides deleted records
        $category = Category::withTrashed()->findOrFail($category_id);

        $this->authorize('restore', $category);

        $restored = $this->categoryService->restore($category_id);

        return $this->success(
            data: new CategoryResource($restored),
            message: 'Category restored successfully.',
        );
    }

    /**
     * @OA\Delete(
     *     path="/categories/force-delete/{book_id}",
     *     tags={"Categories"},
     *     summary="Permanently delete a category (irreversible)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="category_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Permanently deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function forceDelete(int $category_id): JsonResponse
    {
        // Must use withTrashed() — default binding hides deleted records
        $category = Category::withTrashed()->findOrFail($category_id);

        $this->authorize('forceDelete', $category);

        // Load count before deletion so the service exception can reference it
        $category->loadCount('books');

        $this->categoryService->forceDelete($category_id);

        // return $this->noContent('Category permanently deleted. This action cannot be undone.');

        return $this->ok(data: null, message: 'Category permanently deleted. This action cannot be undone.');
    }
}
