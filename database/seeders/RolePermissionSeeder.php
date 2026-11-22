<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Spatie caches permissions; reset it so a re-seed is picked up immediately.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'sanctum';

        $permissions = [
            // Authors
            'author.list', 'author.view', 'author.create', 'author.update',
            'author.delete', 'author.restore', 'author.force_delete',

            // Categories
            'category.list', 'category.view', 'category.create', 'category.update',
            'category.delete', 'category.restore', 'category.force_delete',

            // Members
            'member.list', 'member.view', 'member.create', 'member.update',
            'member.delete', 'member.restore', 'member.force_delete',

            // Borrowings
            // list/view: every role (a member only ever sees their own loans).
            // view_any: see everyone's loans.  create: check a book out.  return: check it back in.
            'borrowing.list', 'borrowing.view', 'borrowing.view_any',
            'borrowing.create', 'borrowing.return',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guard]);
        }

        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => $guard]);
        $owner->syncPermissions(Permission::where('guard_name', $guard)->get()); // owner gets everything

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        $admin->syncPermissions([

            'author.list', 'author.view', 'author.create', 'author.update',
            'author.delete', 'author.restore', 'author.force_delete',

            'category.list', 'category.view', 'category.create', 'category.update',
            'category.delete', 'category.restore', 'category.force_delete',

            'member.list', 'member.view', 'member.create', 'member.update',
            'member.delete', 'member.restore', 'member.force_delete',

            'borrowing.list', 'borrowing.view', 'borrowing.view_any',
            'borrowing.create', 'borrowing.return',

        ]);

        $librarian = Role::firstOrCreate(['name' => 'librarian', 'guard_name' => $guard]);
        $librarian->syncPermissions([

            // No force_delete for librarian — that stays owner/admin only,
            // same convention as staff.delete being withheld above.
            'author.list', 'author.view', 'author.create', 'author.update',
            'author.delete', 'author.restore',

            'category.list', 'category.view', 'category.create', 'category.update',
            'category.delete', 'category.restore',

            'member.list', 'member.view', 'member.create', 'member.update',
            'member.delete', 'member.restore',

            // Borrow, return, view all.
            'borrowing.list', 'borrowing.view', 'borrowing.view_any',
            'borrowing.create', 'borrowing.return',

        ]);

        $member = Role::firstOrCreate(['name' => 'member', 'guard_name' => $guard]);
        $member->syncPermissions([
            'author.list', 'author.view',
            'category.list', 'category.view',
            'member.view', 'member.update',

            // View own loans only (enforced in BorrowingPolicy / BorrowingService).
            'borrowing.list', 'borrowing.view',
        ]);
    }
}