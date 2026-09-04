<?php

namespace App\AI\Tools;

use App\AI\Data\BookMatch;
use App\AI\Data\CatalogSearchResult;
use App\AI\Data\SearchIntentData;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The Assistant's entire read-only surface against the real catalog/loan
 * data — search_catalog, get_book_details, check_availability, get_my_loans,
 * get_library_summary. Every method returns real Eloquent rows or nothing;
 * there is no method here that can hand the caller data that isn't a real
 * database record. Mutating actions (borrow/return/delete/role change) are
 * deliberately NOT exposed here — those stay explicit product actions,
 * never reachable through the Assistant. See ARCHITECTURE.md §11.
 */
class LibraryTools
{
    /** similarity() score at/above this is treated as a real, confident match. */
    private const FUZZY_CONFIDENT = 0.35;

    public function searchCatalog(SearchIntentData $intent): CatalogSearchResult
    {
        $keywordTerm = trim(implode(' ', $intent->keywords));

        $strict = $this->baseQuery($intent)
            ->when($keywordTerm !== '', fn (Builder $q) => $q->search($keywordTerm))
            ->limit(20)
            ->get();

        if ($strict->isNotEmpty() || $keywordTerm === '') {
            return new CatalogSearchResult(books: $strict);
        }

        // The strict full-text search found nothing for a non-empty keyword
        // term — fall back to typo/word-tolerant trigram matching before
        // giving up. Still real rows; just a looser retrieval strategy.
        $fuzzyCandidates = $this->baseQuery($intent)->fuzzy($keywordTerm)->limit(10)->get();
        $confident = $fuzzyCandidates->filter(fn (Book $b) => ($b->fuzzy_score ?? 0) >= self::FUZZY_CONFIDENT)->values();

        if ($confident->isNotEmpty()) {
            return new CatalogSearchResult(books: $confident, usedFuzzy: true);
        }

        return new CatalogSearchResult(books: collect(), usedFuzzy: true, suggestion: $fuzzyCandidates->first());
    }

    public function getBookDetails(string $titleGuess): BookMatch
    {
        return $this->resolveBook($titleGuess);
    }

    public function checkAvailability(string $titleGuess): BookMatch
    {
        return $this->resolveBook($titleGuess);
    }

    /** @return Collection<int, Loan> */
    public function getMyLoans(User $user): Collection
    {
        return Loan::query()
            ->with('book')
            ->where('user_id', $user->id)
            ->orderByDesc('borrowed_at')
            ->limit(20)
            ->get();
    }

    /**
     * Staff-only aggregate counts. Callers MUST check $user->isStaff() before
     * invoking this — it does not check authorization itself, matching the
     * rest of this codebase's pattern of enforcing authorization at the
     * boundary (Policies/middleware for HTTP, ActionClassifier + the caller
     * here for the Assistant) rather than inside every leaf method.
     *
     * @return array{total: int, available: int, borrowed: int, overdue: int}
     */
    public function getLibrarySummary(): array
    {
        return [
            'total' => Book::query()->count(),
            'available' => Book::query()->available()->count(),
            'borrowed' => Book::query()->borrowed()->count(),
            'overdue' => Loan::query()->overdue()->count(),
        ];
    }

    private function resolveBook(string $titleGuess): BookMatch
    {
        $term = trim($titleGuess);
        if ($term === '') {
            return BookMatch::none();
        }

        $exact = Book::query()->withAvailability()->with('tags')->search($term)->first();
        if ($exact) {
            return new BookMatch(book: $exact, confident: true);
        }

        $fuzzy = Book::query()->withAvailability()->with('tags')->fuzzy($term)->first();
        if (! $fuzzy) {
            return BookMatch::none();
        }

        $score = $fuzzy->fuzzy_score ?? 0;

        return new BookMatch(book: $fuzzy, confident: $score >= self::FUZZY_CONFIDENT, score: $score);
    }

    /** @return Builder<Book> */
    private function baseQuery(SearchIntentData $intent): Builder
    {
        return Book::query()
            ->withAvailability()
            ->with('tags')
            ->when($intent->author, fn (Builder $q) => $q->where('author', 'ilike', '%'.$intent->author.'%'))
            ->when($intent->isbn, fn (Builder $q) => $q->isbn($intent->isbn))
            ->when($intent->tags !== [], fn (Builder $q) => $q->whereHas(
                'tags',
                fn (Builder $tagQuery) => $tagQuery->whereIn('name', $intent->tags)
            ))
            ->when($intent->availability === 'available', fn (Builder $q) => $q->available())
            ->when($intent->availability === 'borrowed', fn (Builder $q) => $q->borrowed())
            ->when($intent->publishedAfter, fn (Builder $q) => $q->where('publication_year', '>=', $intent->publishedAfter))
            ->when($intent->publishedBefore, fn (Builder $q) => $q->where('publication_year', '<=', $intent->publishedBefore));
    }
}
