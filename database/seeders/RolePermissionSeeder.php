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
            // Authors
            'author.list', 'author.view', 'author.create', 'author.update',
            'author.delete', 'author.restore', 'author.force_delete',
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

        ]);

        $librarian = Role::firstOrCreate(['name' => 'librarian', 'guard_name' => $guard]);
        $librarian->syncPermissions([

            // No force_delete for librarian — that stays owner/admin only,
            // same convention as staff.delete being withheld above.
            'author.list', 'author.view', 'author.create', 'author.update',
            'author.delete', 'author.restore',

        ]);

        $member = Role::firstOrCreate(['name' => 'member', 'guard_name' => $guard]);
        $member->syncPermissions([

            'author.list', 'author.view',
        ]);
    }
}
