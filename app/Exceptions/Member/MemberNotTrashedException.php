<?php

declare(strict_types=1);

namespace App\Exceptions\Member;

use App\Models\Member;
use Exception;

class MemberNotTrashedException extends Exception
{
    public function __construct(Member $member)
    {
        parent::__construct(
            "Member [{$member->name}] is not soft-deleted. Only soft-deleted members can be restored or force-deleted."
        );
    }
}