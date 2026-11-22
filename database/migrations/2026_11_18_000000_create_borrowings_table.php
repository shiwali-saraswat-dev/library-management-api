<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowings', function (Blueprint $table): void {
            $table->id();

            // restrictOnDelete: a loan is a ledger record, so its book/people can never vanish under it.
            $table->foreignId('book_id')->constrained('books')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('returned_to')->nullable()->constrained('users')->restrictOnDelete();

            $table->dateTime('borrowed_at');
            $table->dateTime('due_at');
            $table->dateTime('returned_at')->nullable();

            // Integer minor units (paise): no float rounding, ever.
            $table->unsignedInteger('fine_minor')->default(0);
            $table->dateTime('fine_calculated_at')->nullable();

            // 1 while the loan is open, NULL once returned. MySQL treats NULLs as distinct in a
            // unique index, so this gives a "partial unique index": at most ONE open loan per
            // (book, member), enforced by the database itself as a backstop to the row locks.
            $table->boolean('is_open')->nullable()->virtualAs('CASE WHEN returned_at IS NULL THEN 1 END');

            $table->timestamps();

            $table->unique(['book_id', 'member_id', 'is_open'], 'borrowings_one_open_loan_per_member_and_book');
            $table->index(['member_id', 'returned_at']);   // "my loans", open-loan count per member
            $table->index(['returned_at', 'due_at']);      // overdue scan used by the fine command
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowings');
    }
};
