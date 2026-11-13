<?php

declare(strict_types=1);

namespace App\Exceptions\Member;

use App\Models\Member;
use Exception;

class MemberHasActiveBorrowingsException extends Exception
{
    public function __construct(Member $member)
    {
        parent::__construct(
            "Member [{$member->name}] has active borrowings and cannot be deleted."
        );
    }
}
