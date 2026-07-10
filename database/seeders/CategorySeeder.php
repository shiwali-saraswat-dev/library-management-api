<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $yesterday = Carbon::yesterday();

        $categories = [

            [
                'name' => 'Fiction',
                'description' => 'Novels and fictional literature.',
            ],

            [
                'name' => 'Fantasy',
                'description' => 'Fantasy and magical adventures.',
            ],

            [
                'name' => 'Science Fiction',
                'description' => 'Books based on futuristic science and technology.',
                'deleted_at' => now()->subDays(10),
            ],

            [
                'name' => 'Mystery',
                'description' => 'Crime, detective and suspense novels.',
            ],

            [
                'name' => 'Thriller',
                'description' => 'Fast-paced suspense stories.',
                'deleted_at' => now()->subDays(10),
            ],

            [
                'name' => 'Romance',
                'description' => 'Love stories and romantic fiction.',
                'deleted_at' => now()->subDays(10),
            ],

            [
                'name' => 'Biography',
                'description' => 'Life stories of famous personalities.',
            ],

            [
                'name' => 'History',
                'description' => 'Historical books and events.',
            ],

            [
                'name' => 'Self Help',
                'description' => 'Personal development and motivation.',
                'deleted_at' => now()->subDays(10),
            ],

            [
                'name' => 'Technology',
                'description' => 'Programming, software engineering and IT.',
            ],

            [
                'name' => 'Business',
                'description' => 'Entrepreneurship, finance and management.',
                'deleted_at' => now()->subDays(10),
            ],

            [
                'name' => 'Children',
                'description' => 'Books for children and young readers.',
            ],
        ];

        foreach ($categories as $category) {

            Category::firstOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true ?? null,
                    'created_at' => $yesterday,
                    'updated_at' => $yesterday,
                    'deleted_at' => $category['deleted_at'] ?? null,
                ]
            );
        }
    }
}
