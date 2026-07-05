<?php

namespace App\Services;

use App\Models\Author;
use App\QueryFilters\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Why a Service class at all?
 * The Controller's job is HTTP plumbing: read the request, call something,
 * shape the response. It should NOT contain query-building or business rules.
 * Putting that here means:
 *   1. The same logic is reusable from a console command, a job, or a test
 *      without spinning up an HTTP request.
 *   2. AuthorController stays thin and readable.
 *   3. You can unit-test AuthorService without touching routes/middleware.
 */
class AuthorService
{
    /**
     * List authors with search, filter, sort, and pagination — all driven
     * by query string parameters, using spatie/laravel-query-builder so we
     * don't hand-roll if/else chains for every combination.
     *
     * Example requests this single method supports:
     *   GET /api/authors?filter[search]=tolkien
     *   GET /api/authors?filter[nationality]=British&sort=-created_at
     *   GET /api/authors?sort=name&per_page=25&page=2
     */
    public function list(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Author::class)
            ->allowedFilters([
                // AllowedFilter::custom maps ?filter[search]=xyz to the
                // scopeSearch() method we defined on the Author model above,
                // instead of doing an exact-match WHERE (which is the
                // library's default behaviour for a plain string filter).
                AllowedFilter::custom('search', new SearchFilter),

                // Plain filters do an exact WHERE country = '...' clause.
                AllowedFilter::exact('country'),
            ])
            ->allowedSorts([
                AllowedSort::field('name'),
                AllowedSort::field('created_at'),
                AllowedSort::field('dob'),
            ])
            // Default sort applied when the client sends no ?sort= at all.
            // Prefixing with '-' elsewhere (e.g. ?sort=-name) means descending.
            ->defaultSort('-created_at')
            ->withCount('books')
            ->paginate($this->perPage($request))
            ->appends($request->query()); // keeps filters/sort in pagination links
    }

    /**
     * Same idea, scoped to only soft-deleted rows, for GET /api/authors/trashed.
     */
    public function listTrashed(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Author::onlyTrashed())
            ->allowedFilters([
                AllowedFilter::custom('search', new SearchFilter),
            ])
            ->allowedSorts(['name', 'deleted_at'])
            ->defaultSort('-deleted_at')
            ->paginate($this->perPage($request))
            ->appends($request->query());
    }

    /**
     * Reads ?per_page= from the client but clamps it so nobody can request
     * per_page=100000 and hammer the database. This kind of guardrail is
     * the sort of detail that separates "works on happy path" from
     * production-grade.
     */
    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', 15);

        return max(1, min($perPage, 100));
    }

    public function create(array $data): Author
    {
        return Author::create($data);
    }

    public function update(Author $author, array $data): Author
    {
        $author->update($data);

        return $author->fresh();
    }

    /**
     * Soft delete. Because Author uses the SoftDeletes trait, ->delete()
     * does NOT remove the row — it just stamps deleted_at. The record is
     * still recoverable and simply excluded from default queries from now on.
     */
    public function delete(Author $author): void
    {
        $author->delete();
    }

    /**
     * Restore a soft-deleted author by id. We deliberately look this up
     * with withTrashed() here rather than relying on route-model-binding,
     * because default binding excludes trashed rows and would 404 before
     * we ever got a chance to restore it.
     */
    public function restore(int $id): Author
    {
        $author = Author::onlyTrashed()->findOrFail($id);
        $author->restore();

        return $author->fresh();
    }

    /**
     * Permanently delete a row — bypasses soft delete entirely.
     * This is intentionally a separate, explicit action (never triggered by
     * the normal DELETE endpoint) so a client can't accidentally nuke data.
     */
    public function forceDelete(int $id): void
    {
        $author = Author::withTrashed()->findOrFail($id);
        $author->forceDelete();
    }

    public function findTrashed(int $id): Author
    {
        return Author::onlyTrashed()->findOrFail($id);
    }
}
