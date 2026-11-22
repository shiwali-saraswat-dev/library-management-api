<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Borrowing;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

/**
 * Database access only. Business rules live in BorrowingService.
 */
final class BorrowingRepository
{
    /** @var list<string> */
    public const RELATIONS = ['book:id,isbn,title', 'member:id,name,email', 'issuedBy:id,name'];

    /**
     * @param  array<string, mixed>  $filters  validated ListBorrowingRequest data
     * @param  int|null  $restrictToMember  when set, only this member's loans are visible
     * @return CursorPaginator<int, Borrowing>
     */
    public function paginate(array $filters, ?int $restrictToMember = null): CursorPaginator
    {
        $direction = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $sortBy = (string) ($filters['sort_by'] ?? 'borrowed_at');
        $perPage = (int) ($filters['per_page'] ?? 15);

        /** @var Builder<Borrowing> $query */
        $query = Borrowing::query()->with(self::RELATIONS);

        // A restricted viewer (a plain member) can never widen the scope with ?member_id=.
        if ($restrictToMember !== null) {
            $query->where('member_id', $restrictToMember);
        } elseif (isset($filters['member_id'])) {
            $query->where('member_id', (int) $filters['member_id']);
        }

        if (isset($filters['book_id'])) {
            $query->where('book_id', (int) $filters['book_id']);
        }

        match ($filters['status'] ?? null) {
            'active' => $query->whereNull('returned_at')->where('due_at', '>=', now()),
            'overdue' => $query->overdue(),
            'returned' => $query->whereNotNull('returned_at'),
            default => null,
        };

        if (isset($filters['search']) && is_string($filters['search']) && $filters['search'] !== '') {
            $like = '%'.addcslashes($filters['search'], '%_\\').'%';

            $query->where(function (Builder $q) use ($like): void {
                $q->whereHas('book', fn (Builder $b) => $b->where(
                    fn (Builder $inner) => $inner->where('title', 'like', $like)->orWhere('isbn', 'like', $like)
                ))->orWhereHas('member', fn (Builder $m) => $m->where(
                    fn (Builder $inner) => $inner->where('name', 'like', $like)->orWhere('email', 'like', $like)
                ));
            });
        }

        // The id tie-breaker keeps cursor pagination stable when sort values collide.
        return $query
            ->orderBy($sortBy, $direction)
            ->orderBy('id', $direction)
            ->cursorPaginate($perPage)
            ->withQueryString();
    }

    public function hydrate(Borrowing $borrowing): Borrowing
    {
        return $borrowing->loadMissing(self::RELATIONS);
    }

    public function bookIdFor(int $borrowingId): int
    {
        return (int) Borrowing::query()->whereKey($borrowingId)->valueOrFail('book_id');
    }

    /** SELECT ... FOR UPDATE. Only call inside a transaction. */
    public function findForUpdate(int $borrowingId): Borrowing
    {
        return Borrowing::query()->lockForUpdate()->findOrFail($borrowingId);
    }

    public function openLoanExists(int $bookId, int $memberId): bool
    {
        return Borrowing::query()->open()->where('book_id', $bookId)->where('member_id', $memberId)->exists();
    }

    public function countOpenLoans(int $memberId): int
    {
        return Borrowing::query()->open()->where('member_id', $memberId)->count();
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Borrowing
    {
        return Borrowing::query()->create($attributes);
    }

    /**
     * Walks open, overdue loans in id order and hands each batch of ids to $callback.
     *
     * @param  Closure(list<int>): void  $callback
     */
    public function eachOverdueChunk(CarbonInterface $asOf, int $size, Closure $callback): void
    {
        Borrowing::query()
            ->overdue($asOf)
            ->select('id')
            ->chunkById($size, function (Collection $chunk) use ($callback): void {
                /** @var list<int> $ids */
                $ids = $chunk->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();

                $callback($ids);
            });
    }

    /**
     * @param  list<int>  $ids
     * @return EloquentCollection<int, Borrowing>
     */
    public function findOpenByIds(array $ids): EloquentCollection
    {
        return Borrowing::query()->open()->whereIn('id', $ids)->get(['id', 'due_at']);
    }

    /**
     * Conditional UPDATE: it only touches the row if the loan is STILL open. If a librarian returned
     * the book a moment ago (finalising the fine), this is a no-op and cannot overwrite that final fine
     * with a stale figure.
     */
    public function updateFineIfOpen(int $borrowingId, int $fineMinor, CarbonInterface $calculatedAt): bool
    {
        return Borrowing::query()
            ->whereKey($borrowingId)
            ->whereNull('returned_at')
            ->update(['fine_minor' => $fineMinor, 'fine_calculated_at' => $calculatedAt]) > 0;
    }
}
