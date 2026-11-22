<?php

declare(strict_types=1);

namespace App\Exceptions\Borrowing;

use App\Exceptions\ApiException;

final class AlreadyBorrowedException extends ApiException
{
    public function __construct()
    {
        parent::__construct('This member already has an open loan for this book.', 'ALREADY_BORROWED', 409);
    }
}
