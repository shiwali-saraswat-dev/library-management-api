<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RecalculateFinesJob;
use App\Repositories\BorrowingRepository;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Scheduler -> this command -> queued jobs (one per chunk) -> workers.
 *
 * The command itself does almost no work: it only finds WHICH loans are overdue and fans them out,
 * so a slow or failing chunk is retried on its own and chunks run in parallel across workers.
 */
class CalculateFinesCommand extends Command
{
    protected $signature = 'library:calculate-fines
                            {--chunk=500 : Borrowings per queued job}
                            {--sync : Run inline instead of queueing (handy on a dev machine with no worker)}';

    protected $description = 'Recalculate fines for every open, overdue borrowing';

    public function handle(BorrowingRepository $borrowings): int
    {
        // One instant for the whole run, so every chunk (and every retry) agrees on "today".
        $asOf = CarbonImmutable::now();
        $size = max(1, (int) $this->option('chunk'));
        $sync = (bool) $this->option('sync');

        $jobs = 0;
        $loans = 0;

        $borrowings->eachOverdueChunk($asOf, $size, function (array $ids) use ($asOf, $sync, &$jobs, &$loans): void {
            $sync
                ? RecalculateFinesJob::dispatchSync($ids, $asOf->toIso8601String())
                : RecalculateFinesJob::dispatch($ids, $asOf->toIso8601String());

            $jobs++;
            $loans += count($ids);
        });

        $this->info(($sync ? 'Processed' : 'Queued')." {$jobs} job(s) covering {$loans} overdue borrowing(s).");

        return self::SUCCESS;
    }
}
