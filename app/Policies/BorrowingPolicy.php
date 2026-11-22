<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Borrowing;
use App\Models\User;

/**
 * Role -> permission mapping lives in RolePermissionSeeder (guard 'sanctum'); the policy only asks questions.
 *
 *  borrowing.list / borrowing.view   every role; a member is limited to their own loans
 *  borrowing.view_any                see every member's loans (staff)
 *  borrowing.create / borrowing.return   check a book out / back in (staff)
 */
final class BorrowingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('borrowing.list');
    }

    public function view(User $user, Borrowing $borrowing): bool
    {
        return $user->can('borrowing.view_any')
            || ($user->can('borrowing.view') && $borrowing->member_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('borrowing.create');
    }

    public function returnBook(User $user, Borrowing $borrowing): bool
    {
        return $user->can('borrowing.return');
    }
}
