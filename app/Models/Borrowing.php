<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\BorrowingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One copy of a book on loan to a member.
 *
 * Deliberately NOT soft-deletable: a loan is a ledger record (it backs fines and audit),
 * and is closed by returning it, never by deleting it.
 *
 * @property int $id
 * @property int $book_id
 * @property int $member_id
 * @property int $issued_by
 * @property int|null $returned_to
 * @property CarbonImmutable $borrowed_at
 * @property CarbonImmutable $due_at
 * @property CarbonImmutable|null $returned_at
 * @property int $fine_minor
 * @property CarbonImmutable|null $fine_calculated_at
 * @property-read string $status
 */
class Borrowing extends Model
{
    /** @use HasFactory<BorrowingFactory> */
    use HasFactory;

    protected $fillable = [
        'book_id',
        'member_id',
        'issued_by',
        'returned_to',
        'borrowed_at',
        'due_at',
        'returned_at',
        'fine_minor',
        'fine_calculated_at',
    ];

    /** @var list<string> */
    protected $hidden = ['is_open'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'borrowed_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'returned_at' => 'immutable_datetime',
            'fine_calculated_at' => 'immutable_datetime',
            'fine_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        // A book may be soft-deleted while old loans still reference it.
        return $this->belongsTo(Book::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return BelongsTo<User, $this> */
    public function returnedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    /** Derived, never stored, so it can never go stale: returned | overdue | active. */
    protected function status(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            $this->returned_at !== null => 'returned',
            $this->due_at->isPast() => 'overdue',
            default => 'active',
        });
    }

    /** @param  Builder<Borrowing>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('returned_at');
    }

    /** @param  Builder<Borrowing>  $query */
    public function scopeOverdue(Builder $query, ?CarbonInterface $asOf = null): void
    {
        $query->whereNull('returned_at')->where('due_at', '<', $asOf ?? now());
    }
}
