<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'owner@library.test'],
            ['name' => 'Library Owner', 'password' => Hash::make('owner@123'), 'is_active' => true]
        );
        $owner->assignRole('owner');

        $admin = User::firstOrCreate(
            ['email' => 'admin@library.test'],
            ['name' => 'Branch Admin', 'password' => Hash::make('admin@123'), 'is_active' => true]
        );
        $admin->assignRole('admin');

        $librarian = User::firstOrCreate(
            ['email' => 'librarian@library.test'],
            ['name' => 'Front Desk Librarian', 'password' => Hash::make('librarian@123'), 'is_active' => true]
        );
        $librarian->assignRole('librarian');

        // A handful of members to borrow/reserve against later
        $members = [
            ['name' => 'Aisha Khan', 'email' => 'aisha@gmail.com'],
            ['name' => 'Rohan Mehta', 'email' => 'rohan@gmail.com'],
            ['name' => 'Priya Nair', 'email' => 'priya@gmail.com'],
        ];

        foreach ($members as $memberData) {
            $member = User::firstOrCreate(
                ['email' => $memberData['email']],
                ['name' => $memberData['name'], 'password' => Hash::make('member@123'), 'is_active' => true]
            );
            $member->assignRole('member');
        }

        // Extra members via factory for volume (useful once you build pagination/filtering)
        User::factory()->count(20)->create()->each(function ($user) {
            $user->assignRole('member');
        });
    }
}
