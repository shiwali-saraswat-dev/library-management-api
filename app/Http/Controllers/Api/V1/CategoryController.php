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

    // =========================================================================
    // GET /api/v1/categories
    // =========================================================================

    /**
     * List categories
     *
     * Returns a cursor-paginated list of categories with search, sort, and filter support.
     * Admins and librarians can also filter trashed (soft-deleted) records.
     *
     * @queryParam search string Search across name and description. Min 2 chars. Example: fiction
     * @queryParam sort_by string Column to sort by. One of: name, sort_order, books_count, created_at, updated_at. Example: name
     * @queryParam sort_dir string Sort direction. One of: asc, desc. Example: asc
     * @queryParam per_page integer Results per page. Min 1, max 100. Default: 15. Example: 20
     * @queryParam is_active boolean Filter by active status. Example: true
     * @queryParam trashed string Admin only. one of: only_deleted, with_deleted. Example: only_deleted
     * @queryParam cursor string Opaque cursor from previous response meta.next_cursor. Example: eyJpZCI6MTV9
     *
     * @response 200 scenario="Success" {
     *   "success": true,
     *   "data": [{"id": 1, "name": "Fiction", "slug": "fiction", "books_count": 42}],
     *   "meta": {"per_page": 15, "next_cursor": "eyJpZCI6MTV9", "has_more": true}
     * }
     */
    public function index(ListCategoryRequest $request): CategoryCollection
    {
        $categories = $this->categoryService->list($request->validated());

        return new CategoryCollection($categories);
    }

    // =========================================================================
    // GET /api/v1/categories/{category_id}
    // =========================================================================

    /**
     * Category details
     *
     * Returns full details for a single active category including books_count,
     * audit fields (admin/librarian only), and role-aware HATEOAS _links.
     *
     * @authenticated
     *
     * @urlParam category_id integer required The category ID. Example: 1
     *
     * @response 200 scenario="Success" {
     *   "success": true,
     *   "data": {"id": 1, "name": "Fiction", "slug": "fiction", "books_count": 42, "_links": {}}
     * }
     * @response 403 scenario="Forbidden — trashed category, non-admin" {"success": false, "message": "You do not have permission to perform this action."}
     * @response 404 scenario="Not found" {"success": false, "message": "Category not found."}
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

    // =========================================================================
    // POST /api/v1/categories
    // =========================================================================

    /**
     * Create a category
     *
     * Creates a new category. Slug is auto-generated from name and guaranteed unique.
     * Requires admin or librarian role. Supports Idempotency-Key header.
     *
     * @authenticated
     *
     * @bodyParam name string required Category name. Min 2, max 100 chars. Example: Science Fiction
     * @bodyParam description string optional Category description. Max 1000 chars. Example: Speculative fiction exploring futuristic concepts.
     * @bodyParam color string optional Hex color for UI badges. Example: #6366F1
     * @bodyParam icon string optional Icon identifier for frontend. Example: book-open
     * @bodyParam is_active boolean optional Defaults to true. Example: true
     * @bodyParam sort_order integer optional Display order 0–9999. Example: 5
     *
     * @header Idempotency-Key string Unique key to prevent duplicate creation on retry. Example: idem_cat_Xy7pQrS
     *
     * @response 201 scenario="Created" {
     *   "success": true,
     *   "data": {"id": 8, "name": "Science Fiction", "slug": "science-fiction"}
     * }
     * @response 409 scenario="Idempotent replay" {"success": true, "X-Idempotent-Replayed": "true"}
     * @response 422 scenario="Validation failed" {"success": false, "message": "Validation failed.", "errors": {}}
     * @response 403 scenario="Forbidden" {"success": false, "message": "You do not have permission to perform this action."}
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

    // =========================================================================
    // PUT /api/v1/categories/{category_id}
    // =========================================================================

    /**
     * Update a category
     *
     * Updates an existing category. All fields are optional (partial update via
     * `sometimes` validation). Slug regenerates automatically when name changes.
     *
     * @authenticated
     *
     * @urlParam category_id integer required The category ID. Example: 1
     *
     * @bodyParam name string Category name. Example: Literary Fiction
     * @bodyParam description string Category description. Example: Updated description.
     * @bodyParam color string Hex color code. Example: #10B981
     * @bodyParam icon string Icon identifier. Example: bookmark
     * @bodyParam is_active boolean Active status. Example: false
     * @bodyParam sort_order integer Display order. Example: 3
     *
     * @response 200 scenario="Updated" {"success": true, "data": {"id": 1, "name": "Literary Fiction"}}
     * @response 404 scenario="Not found" {"success": false, "message": "Category not found."}
     * @response 403 scenario="Forbidden" {"success": false, "message": "You do not have permission to perform this action."}
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

    // =========================================================================
    // DELETE /api/v1/categories/{category_id}
    // =========================================================================

    /**
     * Soft delete a category
     *
     * Moves the category to trash (sets deleted_at). Hidden from public listing
     * but restorable. Blocked if the category has books attached. Requires admin role.
     *
     * @authenticated
     *
     * @urlParam category_id integer required The category ID. Example: 1
     *
     * @response 200 scenario="Soft deleted" {"success": true, "message": "Category moved to trash."}
     * @response 409 scenario="Has books" {"success": false, "message": "Category has 3 book(s) assigned."}
     * @response 404 scenario="Not found" {"success": false, "message": "Category not found."}
     * @response 403 scenario="Forbidden" {"success": false, "message": "You do not have permission to perform this action."}
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

    // =========================================================================
    // POST /api/v1/categories/{category_id}/restore
    // =========================================================================

    /**
     * Restore a soft-deleted category
     *
     * Restores a trashed category back to active state. The category_id must
     * belong to a soft-deleted record. Use GET /categories?trashed=only_deleted
     * to discover deleted category IDs. Requires admin role.
     *
     * @authenticated
     *
     * @urlParam category_id integer required The ID of the soft-deleted category. Example: 5
     *
     * @response 200 scenario="Restored" {"success": true, "data": {"id": 5, "name": "Fiction"}}
     * @response 409 scenario="Not trashed" {"success": false, "message": "Category is not soft-deleted."}
     * @response 404 scenario="Not found" {"success": false, "message": "Category not found."}
     * @response 403 scenario="Forbidden" {"success": false, "message": "You do not have permission to perform this action."}
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

    // =========================================================================
    // DELETE /api/v1/categories/{category_id}/force
    // =========================================================================

    /**
     * Force delete a category (permanent)
     *
     * Permanently removes a category from the database. IRREVERSIBLE.
     * Safety gate: the category MUST be soft-deleted first. You cannot
     * force-delete an active category — soft-delete it first, then force-delete.
     *
     * @authenticated
     *
     * @urlParam category_id integer required The ID of the soft-deleted category. Example: 5
     *
     * @response 200 scenario="Force deleted" {"success": true, "message": "Category permanently deleted."}
     * @response 409 scenario="Not trashed" {"success": false, "message": "Category is not soft-deleted."}
     * @response 409 scenario="Has books" {"success": false, "message": "Category has books assigned."}
     * @response 404 scenario="Not found" {"success": false, "message": "Category not found."}
     * @response 403 scenario="Forbidden" {"success": false, "message": "You do not have permission to perform this action."}
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
