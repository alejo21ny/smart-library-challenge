<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->string('isbn')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->smallInteger('publication_year')->nullable();
            $table->string('cover_url')->nullable();
            $table->timestamps();

            $table->index('title');
            $table->index('author');
            $table->index('category');
            $table->index('publication_year');
        });

        // PostgreSQL full-text search: a generated, always-in-sync tsvector column
        // weighted title > author > description, with a GIN index for fast search.
        // No application-side sync code needed — Postgres maintains this itself.
        DB::statement(<<<'SQL'
            ALTER TABLE books ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('english', coalesce(author, '')), 'B') ||
                setweight(to_tsvector('english', coalesce(description, '')), 'C')
            ) STORED
        SQL);

        DB::statement('CREATE INDEX books_search_vector_idx ON books USING GIN (search_vector)');
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
