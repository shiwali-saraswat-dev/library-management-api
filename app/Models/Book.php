<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'isbn', 'title', 'author_id', 'category_id', 'genre', 'total_copies', 'available_copies', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'date',
        'total_copies' => 'integer',
        'available_copies' => 'integer',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isAvailable(): bool
    {
        return $this->available_copies > 0;
    }
}
