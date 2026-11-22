<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Borrowing>
 */
class BorrowingFactory extends Factory
{
    protected $model = Borrowing::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $borrowedAt = CarbonImmutable::now()->subDays(2);

        return [
            'book_id' => Book::factory(),
            'member_id' => User::factory(),
            'issued_by' => User::factory(),
            'returned_to' => null,
            'borrowed_at' => $borrowedAt,
            'due_at' => $borrowedAt->addDays(14)->endOfDay(),
            'returned_at' => null,
            'fine_minor' => 0,
            'fine_calculated_at' => null,
        ];
    }

    /** Open loan whose due date passed $days calendar days ago. */
    public function overdue(int $days = 3): static
    {
        return $this->state(fn (): array => [
            'borrowed_at' => CarbonImmutable::now()->subDays(14 + $days),
            'due_at' => CarbonImmutable::now()->subDays($days)->endOfDay(),
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (): array => [
            'returned_at' => CarbonImmutable::now()->subDay(),
        ]);
    }
}
