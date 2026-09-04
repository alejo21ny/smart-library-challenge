<?php

namespace App\Models;

use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Availability is intentionally NOT a persisted column. The active Loan
 * (loans.returned_at IS NULL) is the single source of truth — see
 * ARCHITECTURE.md "Availability Refinement". `availability`/`isAvailable()`
 * below are computed from that relation, never stored, so they can never drift.
 *
 * @property-read string $availability 'available' | 'borrowed', computed — see below
 */
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'author',
        'isbn',
        'description',
        'category',
        'publication_year',
        'cover_url',
    ];

    /** @var list<string> */
    protected $appends = ['availability'];

    /** @var list<string> */
    protected $hidden = ['search_vector'];

    /** @var array<string, string> */
    protected $casts = [
        'publication_year' => 'integer',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /** The single active loan for this book, if any — null means available. */
    public function activeLoan(): HasOne
    {
        return $this->hasOne(Loan::class)->whereNull('returned_at');
    }

    protected function availability(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->resolvedActiveLoan() ? 'borrowed' : 'available',
        )->shouldCache();
    }

    public function isAvailable(): bool
    {
        return $this->resolvedActiveLoan() === null;
    }

    private function resolvedActiveLoan(): ?Loan
    {
        // Use the eager-loaded relation when present (avoids N+1 in listings);
        // fall back to a direct query only when accessed in isolation.
        return $this->relationLoaded('activeLoan')
            ? $this->getRelation('activeLoan')
            : $this->activeLoan()->first();
    }

    /** Eager-load what's needed to compute availability without N+1 queries. */
    public function scopeWithAvailability(Builder $query): Builder
    {
        return $query->with('activeLoan');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereDoesntHave('activeLoan');
    }

    public function scopeBorrowed(Builder $query): Builder
    {
        return $query->whereHas('activeLoan');
    }

    /** PostgreSQL full-text search against the generated `search_vector` column. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query
            ->whereRaw("search_vector @@ plainto_tsquery('english', ?)", [$term])
            ->orderByRaw("ts_rank(search_vector, plainto_tsquery('english', ?)) DESC", [$term]);
    }

    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        return blank($category) ? $query : $query->where('category', $category);
    }

    public function scopeTag(Builder $query, ?string $tagName): Builder
    {
        return blank($tagName) ? $query : $query->whereHas('tags', fn (Builder $q) => $q->where('name', $tagName));
    }

    public function scopeIsbn(Builder $query, ?string $isbn): Builder
    {
        return blank($isbn) ? $query : $query->where('isbn', $isbn);
    }

    /**
     * Typo/word-order-tolerant fallback for when the strict full-text search
     * in scopeSearch() finds nothing — e.g. "arquitecture clea" still finding
     * "Clean Architecture". Adds a `fuzzy_score` (0-1) attribute so callers can
     * decide whether a match is confident enough to show as a real result, or
     * only as a "did you mean" suggestion. Requires the pg_trgm extension.
     */
    public function scopeFuzzy(Builder $query, string $term): Builder
    {
        return $query
            ->selectRaw('books.*, GREATEST(similarity(title, ?), similarity(author, ?)) as fuzzy_score', [$term, $term])
            ->whereRaw('(similarity(title, ?) > 0.15 OR similarity(author, ?) > 0.15)', [$term, $term])
            ->orderByDesc('fuzzy_score');
    }
}
