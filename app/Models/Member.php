<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A library member is a row in `users` that holds the `member` role.
 * The global scope below keeps staff accounts (owner/admin/librarian)
 * out of every Member query.
 */
class Member extends Model
{
    use HasFactory, SoftDeletes;

    /** Spatie role name that identifies a member. ADJUST if your role is named differently. */
    public const ROLE = 'member';

    /** Collision-free number derived from the primary key, e.g. MEM-00042. */
    public static function membershipNumberFor(int $userId): string
    {
        return sprintf('MEM-%05d', $userId);
    }

    protected $table = 'users';

    protected $fillable = [
        'name', 'email', 'phone', 'membership_number', 'is_active',
        'password', 'created_by', 'updated_by',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('member_role', function (Builder $builder): void {
            $pivot = config('permission.table_names.model_has_roles', 'model_has_roles');
            $roles = config('permission.table_names.roles', 'roles');

            $builder->whereIn(
                $builder->qualifyColumn('id'),
                fn ($query) => $query->select('model_id')
                    ->from($pivot)
                    ->where('model_type', (new User)->getMorphClass())
                    ->whereIn(
                        'role_id',
                        fn ($role) => $role->select('id')->from($roles)->where('name', self::ROLE)
                    )
            );
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class, 'member_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('membership_number', 'like', "%{$term}%");
        });
    }

    public function scopeSorted(Builder $query, string $column = 'name', string $direction = 'asc'): Builder
    {
        $allowed = ['name', 'email', 'created_at', 'updated_at'];
        $column = in_array($column, $allowed, true) ? $column : 'name';
        $dir = $direction === 'desc' ? 'desc' : 'asc';

        // Natural sort for the name column — see Category module for rationale.
        if ($column === 'name') {
            return $query
                ->orderByRaw("LOWER(TRIM(TRAILING '0123456789' FROM name)) {$dir}")
                ->orderByRaw(
                    "CAST(SUBSTRING(name, LENGTH(TRIM(TRAILING '0123456789' FROM name)) + 1) AS UNSIGNED) {$dir}"
                );
        }

        return $query->orderBy($column, $dir);
    }
}