<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Borrowing;
use App\Services\FineCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin Borrowing
 */
class BorrowingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $daysOverdue = app(FineCalculator::class)->daysOverdue($this->due_at, $this->returned_at ?? now());

        return [
            'id' => $this->id,
            'status' => $this->status,
            'borrowed_at' => $this->borrowed_at->toIso8601String(),
            'due_at' => $this->due_at->toIso8601String(),
            'returned_at' => $this->returned_at?->toIso8601String(),
            'days_overdue' => $daysOverdue,
            // Open loans: refreshed nightly by library:calculate-fines. Returned loans: final.
            'fine' => [
                'amount' => number_format($this->fine_minor / 100, 2, '.', ''),
                'currency' => config('library.currency'),
                'calculated_at' => $this->fine_calculated_at?->toIso8601String(),
            ],
            'book' => $this->whenLoaded('book', fn (): array => [
                'id' => $this->book->id,
                'isbn' => $this->book->isbn,
                'title' => $this->book->title,
            ]),
            'member' => $this->whenLoaded('member', fn (): array => [
                'id' => $this->member->id,
                'name' => $this->member->name,
                'email' => $this->member->email,
            ]),
            'issued_by' => $this->whenLoaded('issuedBy', fn (): array => [
                'id' => $this->issuedBy->id,
                'name' => $this->issuedBy->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            '_links' => array_filter([
                'self' => ['href' => route('borrowings.show', $this->id), 'method' => 'GET'],
                'return' => $this->returned_at === null && Gate::allows('returnBook', $this->resource)
                    ? ['href' => route('borrowings.return', $this->id), 'method' => 'POST']
                    : null,
            ]),
        ];
    }
}
