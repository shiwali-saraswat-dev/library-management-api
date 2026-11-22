<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\BorrowingRepository;
use App\Services\FineCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Recalculates the fine for one chunk of open, overdue loans.
 *
 * Safe to retry or run twice: the fine is computed ABSOLUTELY from (due date, as-of date), and the
 * write is a conditional UPDATE that skips loans returned in the meantime.
 */
final class RecalculateFinesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  list<int>  $borrowingIds
     * @param  string  $asOf  ISO-8601 instant the whole run is calculated "as of" (identical across chunks and retries)
     */
    public function __construct(
        public readonly array $borrowingIds,
        public readonly string $asOf,
    ) {}

    /** @return list<int> seconds to wait before each retry */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(BorrowingRepository $borrowings, FineCalculator $fines): void
    {
        $asOf = CarbonImmutable::parse($this->asOf);

        foreach ($borrowings->findOpenByIds($this->borrowingIds) as $borrowing) {
            $borrowings->updateFineIfOpen(
                $borrowing->id,
                $fines->calculate($borrowing->due_at, $asOf),
                $asOf,
            );
        }
    }
}
