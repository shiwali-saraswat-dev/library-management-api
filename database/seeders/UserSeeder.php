<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'owner@library.test', 'phone' => '9999999990'],
            ['name' => 'Library Owner', 'password' => Hash::make('owner@123'), 'is_active' => true]
        );
        $owner->assignRole('owner');

        $admin = User::firstOrCreate(
            ['email' => 'admin@library.test', 'phone' => '9999999991'],
            ['name' => 'Branch Admin', 'password' => Hash::make('admin@123'), 'is_active' => true]
        );
        $admin->assignRole('admin');

        $librarian = User::firstOrCreate(
            ['email' => 'librarian@library.test', 'phone' => '9999999992'],
            ['name' => 'Front Desk Librarian', 'password' => Hash::make('librarian@123'), 'is_active' => true]
        );
        $librarian->assignRole('librarian');

        // A handful of members to borrow/reserve against later
        $members = [
            ['name' => 'Aisha Khan', 'email' => 'aisha@gmail.com', 'phone' => '9999999993'],
            ['name' => 'Rohan Mehta', 'email' => 'rohan@gmail.com', 'phone' => '9999999994'],
            ['name' => 'Priya Nair', 'email' => 'priya@gmail.com', 'phone' => '9999999995'],
        ];

        foreach ($members as $memberData) {
            $member = User::firstOrCreate(
                ['email' => $memberData['email']],
                [
                    'name' => $memberData['name'],
                    'phone' => $memberData['phone'],
                    'password' => Hash::make('member@123'),
                    'is_active' => true,
                ]
            );
            $this->setUpMember($member, $librarian);
        }

        // Extra members via factory for volume (useful for pagination/filtering)
        User::factory()->count(20)->create()->each(
            fn (User $user) => $this->setUpMember($user, $librarian)
        );
    }

    /**
     * Give a user the member role, a membership number, and an audit trail
     * (members are registered by the front-desk librarian).
     */
    private function setUpMember(User $user, User $registeredBy): void
    {
        $user->assignRole(Member::ROLE);

        if ($user->membership_number === null) {
            $user->forceFill([
                'membership_number' => Member::membershipNumberFor($user->id),
                'created_by' => $registeredBy->id,
                'updated_by' => $registeredBy->id,
            ])->save();
        }
    }
}