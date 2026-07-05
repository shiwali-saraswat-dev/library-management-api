<?php

namespace App\Policies;

use App\Models\Author;
use App\Models\User;

/**
 * A Policy centralizes "is this user allowed to do X" in one class per model.
 * The controller just calls $this->authorize('update', $author) and Laravel
 * automatically resolves to this class's update() method — no if/else
 * scattered across controller methods.
 *
 * Each method here delegates to a Spatie permission string. Create these
 * permissions in your PermissionSeeder:
 *   author-list, author-view, author-create, author-update,
 *   author-delete, author-restore, author-force-delete
 * and assign them to roles as appropriate.
 */
class AuthorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('author.list');
    }

    public function view(User $user, Author $author): bool
    {
        return $user->can('author.view');
    }

    public function create(User $user): bool
    {
        return $user->can('author.create');
    }

    public function update(User $user, Author $author): bool
    {
        return $user->can('author.update');
    }

    public function delete(User $user, Author $author): bool
    {
        return $user->can('author.delete');
    }

    public function restore(User $user): bool
    {
        return $user->can('author.restore');
    }

    public function forceDelete(User $user): bool
    {
        return $user->can('author.force_delete');
    }
}
