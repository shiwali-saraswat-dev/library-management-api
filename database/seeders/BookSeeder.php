<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $books = [

            [
                'isbn' => '9780143127550',
                'title' => 'The Goldfinch',
                'category_id' => 1,
                'author_id' => 1,
                'genre' => 'Fiction',
                'total_copies' => 5,
                'available_copies' => 2,
                'published_at' => '2013-10-22',
                'deleted_at' => null,
            ],

            [
                'isbn' => '9780439708180',
                'title' => 'Harry Potter and the Sorcerer\'s Stone',
                'category_id' => 2,
                'author_id' => 2,
                'genre' => 'Fantasy',
                'total_copies' => 10,
                'available_copies' => 8,
                'published_at' => '1997-06-26',
                'deleted_at' => null,
            ],

            [
                'isbn' => '9780451524935',
                'title' => '1984',
                'category_id' => 3,
                'author_id' => 3,
                'genre' => 'Dystopian',
                'total_copies' => 6,
                'available_copies' => 1,
                'published_at' => '1949-06-08',
                // soft-deleted: exercises the trashed()/restore() endpoints in Postman
                'deleted_at' => '2024-11-15 10:00:00',
            ],

            [
                'isbn' => '9780062315007',
                'title' => 'The Alchemist',
                'category_id' => 4,
                'author_id' => 4,
                'genre' => 'Adventure',
                'total_copies' => 7,
                'available_copies' => 4,
                'published_at' => '1988-05-01',
                'deleted_at' => null,
            ],

            [
                'isbn' => '9780061120084',
                'title' => 'To Kill a Mockingbird',
                'category_id' => 5,
                'author_id' => 5,
                'genre' => 'Classic',
                'total_copies' => 8,
                'available_copies' => 5,
                'published_at' => '1960-07-11',
                'deleted_at' => null,
            ],

            [
                'isbn' => '9780141439518',
                'title' => 'Pride and Prejudice',
                'category_id' => 6,
                'author_id' => 6,
                'genre' => 'Romance',
                'total_copies' => 4,
                'available_copies' => 3,
                'published_at' => '1813-01-28',
                'deleted_at' => null,
            ],

            [
                'isbn' => '9780307474278',
                'title' => 'The Da Vinci Code',
                'category_id' => 7,
                'author_id' => 7,
                'genre' => 'Thriller',
                'total_copies' => 9,
                'available_copies' => 7,
                'published_at' => '2003-03-18',
                // soft-deleted
                'deleted_at' => '2025-02-01 09:30:00',
            ],

            [
                'isbn' => '9780062316097',
                'title' => 'Sapiens',
                'category_id' => 8,
                'author_id' => 8,
                'genre' => 'History',
                'total_copies' => 5,
                'available_copies' => 5,
                'published_at' => '2011-01-01',
                'deleted_at' => null,
            ],

        ];

        foreach ($books as $book) {

            $record = Book::create([
                'isbn' => $book['isbn'],
                'title' => $book['title'],
                'author_id' => $book['author_id'],
                'category_id' => $book['category_id'],
                'genre' => $book['genre'],
                'total_copies' => $book['total_copies'],
                'available_copies' => $book['available_copies'],
                'published_at' => $book['published_at'],
            ]);

            // deleted_at deliberately excluded from create() above — it's not
            // (and shouldn't be) fillable. Set it directly here instead, bypassing
            // mass-assignment protection just for this seeder-only backfill.
            if ($book['deleted_at'] !== null) {
                $record->forceFill(['deleted_at' => $book['deleted_at']])->save();
            }
        }
    }
}
