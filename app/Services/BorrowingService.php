<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Borrowing\AlreadyBorrowedException;
use App\Exceptions\Borrowing\BookNotAvailableException;
use App\Exceptions\Borrowing\BorrowingAlreadyReturnedException;
use App\Exceptions\Borrowing\LoanLimitReachedException;
use App\Exceptions\Borrowing\MemberNotEligibleException;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Member;
use App\Models\User;
use App\Repositories\BorrowingRepository;
use App\Support\BooksCache;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

/**
 * Business rules for lending. Every state change runs in ONE transaction with pessimistic row locks.
 *
 * Lock order is always  book -> member  (checkout) and  book -> borrowing  (return).
 * A single global order means two transactions can never wait on each other in a cycle, so no deadlocks.
 * DB::transaction(..., attempts: 3) retries the rare deadlock MySQL may still detect.
 */
final class BorrowingService
{
    public function __construct(
        private readonly BorrowingRepository $borrowings,
        private readonly FineCalculator $fines,
        private readonly BooksCache $booksCache,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return CursorPaginator<int, Borrowing>
     */
    public function list(array $filters, User $actor): CursorPaginator
    {
        // Staff see every loan; a member only ever sees their own.
        $restrictTo = $actor->can('borrowing.view_any') ? null : $actor->id;

        return $this->borrowings->paginate($filters, $restrictTo);
    }

    public function show(Borrowing $borrowing): Borrowing
    {
        return $this->borrowings->hydrate($borrowing);
    }

    public function checkout(int $bookId, int $memberId, User $issuedBy): Borrowing
    {
        try {
            $borrowing = DB::transaction(function () use ($bookId, $memberId, $issuedBy): Borrowing {
                // 1) Lock the book row. Concurrent checkouts of the SAME book queue up here, and each one
                //    re-reads available_copies after the previous one committed. This is what prevents
                //    two librarians from both taking the last copy.
                $book = Book::query()->lockForUpdate()->findOrFail($bookId);

                // 2) Lock the member row, so one member's concurrent checkouts cannot both slip
                //    past the max-active-loans limit.
                $member = Member::query()->lockForUpdate()->findOrFail($memberId);

                if (! $member->is_active) {
                    throw new MemberNotEligibleException('the account is inactive.');
                }

                if ($this->borrowings->openLoanExists($book->id, $member->id)) {
                    throw new AlreadyBorrowedException();
                }

                $limit = (int) config('library.max_active_loans');

                if ($this->borrowings->countOpenLoans($member->id) >= $limit) {
                    throw new LoanLimitReachedException($limit);
                }

                if ($book->available_copies < 1) {
                    throw new BookNotAvailableException($book->title);
                }

                $now = CarbonImmutable::now();

                $borrowing = $this->borrowings->create([
                    'book_id' => $book->id,
                    'member_id' => $member->id,
                    'issued_by' => $issuedBy->id,
                    'borrowed_at' => $now,
                    // Due at the END of the due day, so "overdue" means "the due DATE has passed".
                    'due_at' => $now->addDays((int) config('library.loan_days'))->endOfDay(),
                ]);

                // Atomic in SQL (available_copies = available_copies - 1), under the row lock.
                $book->decrement('available_copies');

                return $borrowing;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            // Backstop: the partial unique index caught a duplicate open loan the checks above missed.
            throw new AlreadyBorrowedException();
        }

        // After commit: the cached catalogue shows available_copies, which just changed.
        $this->booksCache->flush();

        return $this->borrowings->hydrate($borrowing);
    }

    public function return(int $borrowingId, User $processedBy): Borrowing
    {
        $borrowing = DB::transaction(function () use ($borrowingId, $processedBy): Borrowing {
            // Same global lock order as checkout: book first, then the borrowing.
            $bookId = $this->borrowings->bookIdFor($borrowingId);

            // withTrashed: a loan must stay returnable even if the book was soft-deleted meanwhile.
            $book = Book::withTrashed()->lockForUpdate()->findOrFail($bookId);

            $borrowing = $this->borrowings->findForUpdate($borrowingId);

            // Re-check UNDER the lock: two simultaneous return requests, only the first wins.
            if ($borrowing->returned_at !== null) {
                throw new BorrowingAlreadyReturnedException();
            }

            $now = CarbonImmutable::now();

            $borrowing->forceFill([
                'returned_at' => $now,
                'returned_to' => $processedBy->id,
                // Final fine, computed once, and never touched again by the nightly job.
                'fine_minor' => $this->fines->calculate($borrowing->due_at, $now),
                'fine_calculated_at' => $now,
            ])->save();

            if ($book->available_copies < $book->total_copies) {
                $book->increment('available_copies');
            }

            return $borrowing;
        }, attempts: 3);

        $this->booksCache->flush();

        return $this->borrowings->hydrate($borrowing);
    }
}
