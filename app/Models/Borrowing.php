<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A borrowing is created when a member takes out a book and closed when it's
 * returned. Rows are never edited or deleted after creation — "active" means
 * returned_at IS NULL, so history always stays intact. See the project plan,
 * section 6 (Borrowing rules), for the full set of business rules enforced
 * elsewhere (3-borrowing cap, 14-day due date, transaction + row lock, etc).
 */
class Borrowing extends Model
{
    use HasFactory;

    protected $table = 'borrowings';

    protected $fillable = [
        'member_id', 'book_id', 'borrowed_at', 'due_at', 'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    // member_id references users.id — the Member model is a scoped view over
    // the users table (see Member::ROLE), so this resolves correctly.
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    // --- Query scopes, matching the GET /borrowings status filter -----------

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('returned_at');
    }

    public function scopeReturned(Builder $query): Builder
    {
        return $query->whereNotNull('returned_at');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNull('returned_at')->where('due_at', '<', now());
    }
}