<?php

namespace App\AI\Data;

use App\Models\Book;

/**
 * The result of resolving a natural-language "which book do you mean"
 * guess (used by get_book_details and check_availability) against the
 * real catalog — never a fabricated book.
 */
final readonly class BookMatch
{
    public function __construct(
        public ?Book $book,
        public bool $confident,
        public ?float $score = null,
    ) {}

    public static function none(): self
    {
        return new self(book: null, confident: false);
    }
}
