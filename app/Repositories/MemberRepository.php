<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Member;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MemberRepository
{
    public function paginate(array $filters): CursorPaginator
    {
        $query = Member::query();

        match ($filters['trashed'] ?? 'none') {
            'only_deleted' => $query->onlyTrashed(),
            'with_deleted' => $query->withTrashed(),
            default => null,
        };

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $query->sorted(
            column: $filters['sort_by'] ?? 'name',
            direction: $filters['sort_dir'] ?? 'asc',
        );

        return $query->cursorPaginate($filters['per_page'] ?? 15);
    }

    public function findById(int $id): ?Member
    {
        return Member::find($id);
    }

    public function findByIdOrFail(int $id): Member
    {
        return Member::findOrFail($id);
    }

    public function findTrashedByIdOrFail(int $id): Member
    {
        return Member::withTrashed()->findOrFail($id);
    }

    public function create(array $data): Member
    {
        return DB::transaction(function () use ($data): Member {
            $member = Member::create($data);

            $member->forceFill([
                'membership_number' => Member::membershipNumberFor($member->id),
            ])->save();

            // Member is not a Spatie model; assign the role through User so the
            // model_has_roles row uses the App\Models\User morph class.
            User::query()->findOrFail($member->id)->assignRole(Member::ROLE);

            return $member->refresh();
        });
    }

    public function update(Member $member, array $data): Member
    {
        $member->update($data);
        return $member->refresh();
    }

    public function softDelete(Member $member): void
    {
        $member->delete();
    }

    public function restore(Member $member): void
    {
        $member->restore();
    }

    public function forceDelete(Member $member): void
    {
        $member->forceDelete();
    }

    public function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        return Member::withTrashed()
            ->where('email', $email)
            ->when($excludeId, fn (Builder $q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    // "Active" means returned_at IS NULL — see Borrowing model's scopeActive().
    public function hasActiveBorrowings(Member $member): bool
    {
        return $member->borrowings()->active()->exists();
    }
}
