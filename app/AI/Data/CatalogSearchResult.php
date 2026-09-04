<?php

namespace App\AI\Data;

use App\Models\Book;
use Illuminate\Support\Collection;

/**
 * @property Collection<int, Book> $books
 */
final readonly class CatalogSearchResult
{
    /**
     * @param  Collection<int, Book>  $books  Real, confident matches — empty if none.
     * @param  bool  $usedFuzzy  True if the strict full-text search found nothing and a typo-tolerant fallback ran.
     * @param  Book|null  $suggestion  A plausible-but-not-confident "did you mean" match, only set when $books is empty.
     */
    public function __construct(
        public Collection $books,
        public bool $usedFuzzy = false,
        public ?Book $suggestion = null,
    ) {}
}
