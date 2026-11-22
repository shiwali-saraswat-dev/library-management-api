<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Config;

/**
 * Pure fine arithmetic: no database, no clock, no side effects, so it is trivial to unit test.
 *
 * The result is ABSOLUTE ("what is owed as of $asOf"), never incremental ("add one more day").
 * That is what makes the nightly job safe to retry or run twice: same input, same output.
 */
final class FineCalculator
{
    public function __construct(
        #[Config('library.fines.per_day_minor')] private readonly int $perDayMinor,
        #[Config('library.fines.max_minor')] private readonly int $maxMinor,
        #[Config('library.fines.grace_days')] private readonly int $graceDays = 0,
    ) {}

    /** Whole calendar days past the due date (the due date itself is not late). */
    public function daysOverdue(CarbonInterface $dueAt, CarbonInterface $asOf): int
    {
        $due = $dueAt->copy()->startOfDay();
        $now = $asOf->copy()->setTimezone($due->getTimezone())->startOfDay();

        if ($now->lessThanOrEqualTo($due)) {
            return 0;
        }

        return max(0, (int) round(abs($due->diffInDays($now))) - $this->graceDays);
    }

    /** Fine in integer minor units (paise), capped at the configured maximum. */
    public function calculate(CarbonInterface $dueAt, CarbonInterface $asOf): int
    {
        return min($this->maxMinor, $this->daysOverdue($dueAt, $asOf) * $this->perDayMinor);
    }
}
