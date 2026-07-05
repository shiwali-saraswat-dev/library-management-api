<?php

namespace Database\Seeders;

use App\Models\Author;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AuthorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $yesterday = Carbon::yesterday();

        $authors = [

            [
                'name' => 'Donna Tartt',
                'country' => 'United States',
                'dob' => '1963-12-23',
            ],

            [
                'name' => 'J. K. Rowling',
                'country' => 'United Kingdom',
                'dob' => '1965-07-31',
            ],

            [
                'name' => 'George Orwell',
                'country' => 'United Kingdom',
                'dob' => '1903-06-25',
                'deleted_at' => now()->subDays(10),
            ],

            [
                'name' => 'Paulo Coelho',
                'country' => 'Brazil',
                'dob' => '1947-08-24',
            ],

            [
                'name' => 'Harper Lee',
                'country' => 'United States',
                'dob' => '1926-04-28',
            ],

            [
                'name' => 'Jane Austen',
                'country' => 'United Kingdom',
                'dob' => '1775-12-16',
                'deleted_at' => now()->subDays(5),
            ],

            [
                'name' => 'Dan Brown',
                'country' => 'United States',
                'dob' => '1964-06-22',
            ],

            [
                'name' => 'Yuval Noah Harari',
                'country' => 'Israel',
                'dob' => '1976-02-24',
            ],
        ];

        foreach ($authors as $author) {

            Author::create(array_merge(
                $author,
                [
                    'created_at' => $yesterday,
                    'updated_at' => $yesterday,
                ]
            ));
        }
    }
}
