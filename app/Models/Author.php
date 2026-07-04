<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Author extends Model
{
    // HasFactory -> lets us do Author::factory() in tests/seeders.
    // SoftDeletes -> instead of a real DELETE, Eloquent sets deleted_at
    // and every normal query automatically excludes trashed rows.
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     * Whitelisting like this (instead of $guarded = []) is the safer default for 
     * an API where the request body is untrusted input.
     * 
     * @var list<string>
     */
    protected $fillable = [
        'name', 'bio', 'dob', 'country',
    ];

    /**
     * Type casting. Casting birth_date to 'date' gives us a Carbon instance
     * automatically instead of a raw string, so $author->birth_date->format(...)
     * just works. deleted_at is cast to datetime so it serializes consistently.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dob' => 'date',
        ];
    }

    /**
     * Relationship: one author has many books.
     * Assumes a `books` table with an `author_id` foreign key.
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
 
    /**
     * Local query scope for free-text search across a few columns.
     * Used by the service layer as a custom filter for spatie/laravel-query-builder.
     * Keeping this on the model (not the controller) means the search logic
     * is reusable and unit-testable in isolation.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('country', 'like', "%{$term}%");
        });
    }

}