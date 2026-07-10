<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Category\CategoryHasBooksException;
use App\Exceptions\Category\CategoryNotTrashedExceptions;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): CursorPaginator
    {
        return $this->categoryRepository->paginate($filters);
    }

    public function findOrFail(int $id): Category
    {
        return $this->categoryRepository->findByIdOrFail($id);
    }

    public function allActive(): Collection
    {
        // Cache the simple dropdown list — it changes rarely
        return Cache::
            // tags(['categories'])->
            remember('categories:active_list', config('library.cache.categories_ttl', 600), fn () => $this->categoryRepository->allActive()
            );
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): Category
    {
        $category = $this->categoryRepository->create([
            ...$data,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $this->bustCache();

        return $category->loadCount('books');
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data, int $userId): Category
    {
        $updated = $this->categoryRepository->update($category, [
            ...$data,
            'updated_by' => $userId,
        ]);

        $this->bustCache();

        return $updated->loadCount('books');
    }

    // -------------------------------------------------------------------------
    // Soft Delete
    // -------------------------------------------------------------------------

    /**
     * Soft-deletes a category.
     * Blocks if the category has books attached — orphaning books is a business violation.
     *
     * @throws CategoryHasBooksException
     */
    public function softDelete(Category $category): void
    {
        if ($this->categoryRepository->hasBooks($category)) {
            throw new CategoryHasBooksException($category);
        }

        $this->categoryRepository->softDelete($category);
        $this->bustCache();
    }

    // -------------------------------------------------------------------------
    // Restore
    // -------------------------------------------------------------------------

    /**
     * Restores a soft-deleted category.
     *
     * @throws CategoryNotTrashedExceptions
     */
    public function restore(int $id): Category
    {
        // Must use withTrashed to find deleted records
        $category = $this->categoryRepository->findTrashedByIdOrFail($id);

        if (! $category->trashed()) {
            throw new CategoryNotTrashedExceptions($category);
        }

        $this->categoryRepository->restore($category);
        $this->bustCache();

        return $category->refresh()->loadCount('books');
    }

    // -------------------------------------------------------------------------
    // Force Delete
    // -------------------------------------------------------------------------

    /**
     * Permanently deletes a category — ONLY allowed on already soft-deleted records.
     * Adds a safety gate: you must soft-delete first, then force-delete.
     * This prevents accidental permanent deletion of active categories.
     *
     * @throws CategoryHasBooksException
     * @throws CategoryNotTrashedExceptions
     */
    public function forceDelete(int $id): void
    {
        $category = $this->categoryRepository->findTrashedByIdOrFail($id);

        // Two-step safety gate: a record must be soft-deleted before it can be
        // permanently destroyed. This prevents accidental, irreversible deletion
        // of live data and gives a recovery window (soft-delete → restore),
        // similar to GitHub repo deletion, Gmail trash, Trello archive → delete.
        if (! $category->trashed()) {
            throw new CategoryNotTrashedExceptions($category);
        }

        if ($this->categoryRepository->hasBooks($category)) {
            throw new CategoryHasBooksException($category);
        }

        $this->categoryRepository->forceDelete($category);
        $this->bustCache();
    }

    // -------------------------------------------------------------------------
    // Cache management
    // -------------------------------------------------------------------------

    private function bustCache(): void
    {
        Cache::
            // tags(['categories'])->
            flush();
    }
}
