<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('borrowed_at');
            $table->timestamp('due_at');
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('returned_at');
            $table->index('due_at');
        });

        // The actual source of truth for "a book cannot have multiple active loans":
        // a unique index that only applies to rows where returned_at IS NULL.
        // This is enforced by PostgreSQL itself — safe even under concurrent requests,
        // not just relying on an application-level check-then-act.
        DB::statement(
            'CREATE UNIQUE INDEX loans_book_id_active_unique ON loans (book_id) WHERE returned_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
