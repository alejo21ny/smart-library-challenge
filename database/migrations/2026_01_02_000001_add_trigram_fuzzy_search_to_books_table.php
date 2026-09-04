<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fuzzy/typo-tolerant matching for the Library Assistant's deterministic
 * fallback (e.g. "arquitecture clea" -> "Clean Architecture"). pg_trgm's
 * similarity() compares trigrams of the whole string, which tolerates
 * misspellings and truncated words far better than the strict tsvector
 * search in the previous migration — used only as a fallback when the
 * exact full-text search returns nothing. See App\AI\Tools\LibraryTools.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX books_title_trgm_idx ON books USING GIN (title gin_trgm_ops)');
        DB::statement('CREATE INDEX books_author_trgm_idx ON books USING GIN (author gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS books_title_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS books_author_trgm_idx');
    }
};
