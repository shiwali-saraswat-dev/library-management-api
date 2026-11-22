<?php

declare(strict_types=1);

namespace App\Exceptions\Borrowing;

use App\Exceptions\ApiException;

final class BorrowingAlreadyReturnedException extends ApiException
{
    public function __construct()
    {
        parent::__construct('This borrowing has already been returned.', 'ALREADY_RETURNED', 409);
    }
}
