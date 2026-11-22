<?php

declare(strict_types=1);

namespace App\Exceptions\Borrowing;

use App\Exceptions\ApiException;

final class MemberNotEligibleException extends ApiException
{
    public function __construct(string $reason)
    {
        parent::__construct("This member cannot borrow: {$reason}", 'MEMBER_NOT_ELIGIBLE', 422);
    }
}
