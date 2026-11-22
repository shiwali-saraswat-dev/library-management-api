<?php

namespace Database\Seeders;

use App\Models\Borrowing;
use App\Models\User;
use App\Models\Book;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BorrowingSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $librarian = User::where('email', 'librarian@library.test')->firstOrFail();
        $goldfinch = Book::where('isbn', '9780143127550')->firstOrFail(); // 5 total, 2 available
        $members = User::role('member')->take(3)->get();                  // 3 copies out

        foreach ($members as $member) {
            Borrowing::create([
                'book_id' => $goldfinch->id,
                'member_id' => $member->id,
                'issued_by' => $librarian->id,
                'borrowed_at' => now()->subDays(5),
                'due_at' => now()->addDays(9),     // 14-day loan
            ]);
        }
    }
}
