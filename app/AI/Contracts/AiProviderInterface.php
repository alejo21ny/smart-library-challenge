<?php

namespace App\AI\Contracts;

use App\AI\Data\SearchIntentData;
use App\Models\Book;
use Illuminate\Support\Collection;

interface AiProviderInterface
{
    /**
     * Turn a natural-language query into structured search parameters.
     * MUST NOT return anything resembling actual book data — only query intent.
     */
    public function extractSearchIntent(string $query): SearchIntentData;

    /**
     * Produce a short "why this matched" explanation, grounded ONLY in the
     * real book rows passed in. Implementations must not introduce books,
     * facts, or details not present in $books.
     *
     * @param  Collection<int, Book>  $books
     */
    public function summarize(string $query, Collection $books): string;
}
