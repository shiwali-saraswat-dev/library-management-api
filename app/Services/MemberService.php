<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Member\MemberHasActiveBorrowingsException;
use App\Exceptions\Member\MemberNotTrashedException;
use App\Models\Member;
use App\Repositories\MemberRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Str;

class MemberService
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
    ) {}

    public function list(array $filters): CursorPaginator
    {
        return $this->memberRepository->paginate($filters);
    }

    public function findOrFail(int $id): Member
    {
        return $this->memberRepository->findByIdOrFail($id);
    }

    // Trashed-aware lookup. Use this (not findOrFail) wherever you need to
    // fetch a member who may already be soft-deleted — e.g. in a controller,
    // before authorizing a restore or forceDelete action.
    public function findTrashedOrFail(int $id): Member
    {
        return $this->memberRepository->findTrashedByIdOrFail($id);
    }

    public function create(array $data, int $userId): Member
    {
        return $this->memberRepository->create([
            ...$data,
            // users.password is NOT NULL. Members are registered by staff, so start
            // with a random unusable password; they set their own via a reset flow.
            'password' => Str::random(40),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function update(Member $member, array $data, int $userId): Member
    {
        return $this->memberRepository->update($member, [
            ...$data,
            'updated_by' => $userId,
        ]);
    }

    public function softDelete(Member $member): void
    {
        if ($this->memberRepository->hasActiveBorrowings($member)) {
            throw new MemberHasActiveBorrowingsException($member);
        }

        $this->memberRepository->softDelete($member);
    }

    public function restore(int $id): Member
    {
        $member = $this->memberRepository->findTrashedByIdOrFail($id);

        // Two-step safety gate — same rationale as Category: restore/force-delete
        // must only act on records that were deliberately soft-deleted first.
        if (! $member->trashed()) {
            throw new MemberNotTrashedException($member);
        }

        $this->memberRepository->restore($member);
        return $member->refresh();
    }

    public function forceDelete(int $id): void
    {
        $member = $this->memberRepository->findTrashedByIdOrFail($id);

        if (! $member->trashed()) {
            throw new MemberNotTrashedException($member);
        }

        if ($this->memberRepository->hasActiveBorrowings($member)) {
            throw new MemberHasActiveBorrowingsException($member);
        }

        $this->memberRepository->forceDelete($member);
    }
}