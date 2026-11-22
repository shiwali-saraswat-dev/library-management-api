<?php

declare(strict_types=1);

namespace App\Exceptions\Borrowing;

use App\Exceptions\ApiException;

final class LoanLimitReachedException extends ApiException
{
    public function __construct(int $limit)
    {
        parent::__construct("This member has reached the limit of {$limit} active loans.", 'LOAN_LIMIT_REACHED', 409);
    }
}
