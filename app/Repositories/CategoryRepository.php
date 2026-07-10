<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository
{
    // =========================================================================
    // List / Search / Sort / Paginate
    // =========================================================================

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): CursorPaginator
    {
        $query = Category::query()
            ->withCount('books');  // books_count is computed via subquery — no column needed

        // Trashed filter
        match ($filters['trashed'] ?? 'none') {
            'only_deleted' => $query->onlyTrashed(),
            'with_deleted' => $query->withTrashed(),
            default => null,
        };

        // Active filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
            // $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Search
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // FIX — default sort changed to 'name' to match scopeSorted fix.
        // sort_order is not a real column, so do not pass it here.
        $query->sorted(
            column: $filters['sort_by'] ?? 'name',  // FIX: was 'sort_order'
            direction: $filters['sort_dir'] ?? 'asc',
        );

        return $query->cursorPaginate($filters['per_page'] ?? 15);
    }

    // =========================================================================
    // Find
    // =========================================================================

    public function findById(int $id): ?Category
    {
        return Category::withCount('books')->find($id);
    }

    public function findByIdOrFail(int $id): Category
    {
        return Category::withCount('books')->findOrFail($id);
    }

    public function findTrashedByIdOrFail(int $id): Category
    {
        return Category::withTrashed()->withCount('books')->findOrFail($id);
    }

    public function findBySlug(string $slug): ?Category
    {
        return Category::where('slug', $slug)->withCount('books')->first();
    }

    /**
     * All active categories as a flat list for dropdowns.
     * FIX — removed color, icon from select() — those columns don't exist yet.
     */
    public function allActive(): Collection
    {
        return Category::active()
            ->sorted('name', 'asc')         // FIX: was sorted('sort_order', 'asc')
            ->get(['id', 'name', 'slug']);   // FIX: removed 'color', 'icon' — don't exist
    }

    // =========================================================================
    // Write
    // =========================================================================

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->refresh();
    }

    public function softDelete(Category $category): void
    {
        $category->delete();
    }

    public function restore(Category $category): void
    {
        $category->restore();
    }

    public function forceDelete(Category $category): void
    {
        $category->forceDelete();
    }

    // =========================================================================
    // Checks
    // =========================================================================

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        return Category::withTrashed()
            ->where('name', $name)
            ->when($excludeId, fn (Builder $q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    public function hasBooks(Category $category): bool
    {
        return $category->books()->exists();
    }
}
