<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Only columns that exist in your current categories table.
     * Do NOT add color, icon, sort_order, books_count, created_by, updated_by
     * until you run a migration adding those columns.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // =========================================================================
    // Boot — auto-generate slug from name
    // =========================================================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $category): void {
            $category->slug = $category->generateUniqueSlug($category->name);
        });

        static::updating(function (self $category): void {
            if ($category->isDirty('name') && ! $category->isDirty('slug')) {
                $category->slug = $category->generateUniqueSlug($category->name, $category->id);
            }
        });
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    // These relationships exist for when you add created_by/updated_by columns later.
    // They are defined now so CategoryResource::whenLoaded() doesn't throw —
    // it simply returns nothing if the relation is not loaded.
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // =========================================================================
    // Query Scopes
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function scopeSorted(Builder $query, string $column = 'name', string $direction = 'asc'): Builder
    {
        $allowed = ['name', 'created_at', 'updated_at', 'books_count'];
        $column = in_array($column, $allowed, true) ? $column : 'name';
        $dir = $direction === 'desc' ? 'desc' : 'asc';

        if ($column === 'name') {
            // Natural sort without REGEXP_SUBSTR (unreliable across MySQL/MariaDB builds).
            // Strip trailing digits to get the prefix, then take the substring
            // AFTER the prefix to get the numeric suffix, and CAST it as an integer.
            return $query
                // 1) Alphabetic prefix, case-insensitive, trailing digits stripped
                ->orderByRaw("LOWER(TRIM(TRAILING '0123456789' FROM name)) {$dir}")
                // 2) Numeric suffix as a real integer — SUBSTRING from just past
                //    the prefix length. Empty string (no digits) casts to 0.
                ->orderByRaw(
                    "CAST(SUBSTRING(name, LENGTH(TRIM(TRAILING '0123456789' FROM name)) + 1) AS UNSIGNED) {$dir}"
                );
        }

        return $query->orderBy($column, $dir);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $count = 1;

        while (
            static::withTrashed()
                ->where('slug', $slug)
                ->when($excludeId, fn (Builder $q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }
}
