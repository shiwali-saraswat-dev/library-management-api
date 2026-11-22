<?php

declare(strict_types=1);

namespace App\Exceptions\Borrowing;

use App\Exceptions\ApiException;

final class BookNotAvailableException extends ApiException
{
    public function __construct(string $title)
    {
        parent::__construct("No copies of \"{$title}\" are available right now.", 'BOOK_NOT_AVAILABLE', 409);
    }
}
