<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('member.list');
    }

    // Nullable user — mirrors the Category fix so guests aren't hard-denied
    // before the policy even runs, if member profiles are ever made public.
    // Tighten to non-nullable User if member data must always require auth.
    public function view(?User $user, Member $member): bool
    {
        return $user?->can('member.view') ?? false;
    }

    public function create(User $user): bool
    {
        return $user->can('member.create');
    }

    public function update(User $user, Member $member): bool
    {
        return $user->can('member.update');
    }

    public function delete(User $user, Member $member): bool
    {
        return $user->can('member.delete');
    }

    public function restore(User $user, Member $member): bool
    {
        return $user->can('member.restore');
    }

    public function forceDelete(User $user, Member $member): bool
    {
        return $user->can('member.force_delete');
    }
}