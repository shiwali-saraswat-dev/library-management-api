<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'sanctum';

        $permissions = [
            // Catalog
            'books.view', 'books.create', 'books.update', 'books.delete',

            // Members
            'members.view', 'members.create', 'members.update', 'members.deactivate',

            // Staff (owner/admin only)
            'staff.view', 'staff.create', 'staff.update', 'staff.delete',

            // Borrowing
            'borrows.create', 'borrows.return', 'borrows.view.own', 'borrows.view.all',

            // Reservations
            'reservations.create', 'reservations.cancel', 'reservations.view.own', 'reservations.view.all',

            // Fines
            'fines.view.own', 'fines.view.all', 'fines.collect', 'fines.waive',

            // Reports / system
            'reports.view', 'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guard]);
        }

        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => $guard]);
        $owner->syncPermissions(Permission::where('guard_name', $guard)->get()); // owner gets everything

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        $admin->syncPermissions([
            'books.view', 'books.create', 'books.update', 'books.delete',
            'members.view', 'members.create', 'members.update', 'members.deactivate',
            'staff.view', 'staff.create', 'staff.update',
            'borrows.create', 'borrows.return', 'borrows.view.all',
            'reservations.view.all', 'reservations.cancel',
            'fines.view.all', 'fines.collect', 'fines.waive',
            'reports.view',
        ]);

        $librarian = Role::firstOrCreate(['name' => 'librarian', 'guard_name' => $guard]);
        $librarian->syncPermissions([
            'books.view', 'books.create', 'books.update',
            'members.view', 'members.create',
            'borrows.create', 'borrows.return', 'borrows.view.all',
            'reservations.create', 'reservations.cancel', 'reservations.view.all',
            'fines.view.all', 'fines.collect',
        ]);

        $member = Role::firstOrCreate(['name' => 'member', 'guard_name' => $guard]);
        $member->syncPermissions([
            'books.view',
            'borrows.view.own',
            'reservations.create', 'reservations.cancel', 'reservations.view.own',
            'fines.view.own',
        ]);
    }
}
