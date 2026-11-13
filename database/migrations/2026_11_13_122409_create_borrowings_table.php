<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('borrowings', function (Blueprint $table) {
            $table->id();
 
            // member_id references users.id — Member is a scoped view over
            // the users table, not a separate table (see Member model).
            $table->foreignId('member_id')->constrained('users')->restrictOnDelete();
 
            $table->foreignId('book_id')->constrained('books')->restrictOnDelete();
 
            // dateTime(), not timestamp(): MySQL only auto-defaults the FIRST
            // not-null TIMESTAMP column in a table; any others need an
            // explicit default or strict mode rejects them (error 1067).
            // dateTime columns don't have this behavior, so they're safer
            // here since neither borrowed_at nor due_at has a real default.
            $table->dateTime('borrowed_at');
            $table->dateTime('due_at');
 
            // null = still out. No separate status column — "active" is
            // derived from this being null (see plan, section 3).
            $table->dateTime('returned_at')->nullable();
 
            $table->timestamps();
 
            // Speeds up GET /borrowings?status=active|overdue and the
            // 3-active-borrowings-per-member check.
            $table->index(['member_id', 'returned_at']);
            $table->index(['returned_at', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrowings');
    }
};
