<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * CategoryPolicy
 *
 * Centralizes "is this user allowed to do X" for the Category model, in one
 * place per action. The controller calls $this->authorize('update', $category)
 * and Laravel automatically resolves to this class's update() method --
 * no if/else scattered across controller methods.
 *
 * Each method here delegates to a Spatie permission string
 * (category.list, category.view, category.create, category.update,
 * category.delete, category.restore, category.force_delete), seeded via
 * RolePermissionSeeder and assigned per role there.
 */
class CategoryPolicy
{
    /**
     * Determines if the user can view the category list/index.
     * Checked before returning any paginated collection of categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('category.list');
    }

    /**
     * Determines if the user can view a single category's details.
     */
    public function view(User $user, Category $category): bool
    {
        return $user->can('category.view');
    }

    /**
     * Determines if the user can create a new category.
     */
    public function create(User $user): bool
    {
        return $user->can('category.create');
    }

    /**
     * Determines if the user can update an existing category.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->can('category.update');
    }

    /**
     * Determines if the user can soft-delete a category.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->can('category.delete');
    }

    /**
     * Determines if the user can restore a soft-deleted category.
     */
    public function restore(User $user, Category $category): bool
    {
        return $user->can('category.restore');
    }

    /**
     * Determines if the user can permanently delete a category.
     * Kept as its own permission, separate from delete(), since this
     * action is irreversible and typically restricted to owner/admin only.
     */
    public function forceDelete(User $user, Category $category): bool
    {
        return $user->can('category.force_delete');
    }
}
