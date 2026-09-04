<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Data\SearchIntentData;
use Illuminate\Support\Collection;

/**
 * Bound whenever no AI provider is configured. No external call, ever —
 * this is what makes "the app works fully without an AI key" literally true.
 * Uses a small set of deterministic rules instead of true NL understanding:
 * still useful, never flaky, never a dead end for reviewers without a key.
 */
class NullAiProvider implements AiProviderInterface
{
    private const STOPWORDS = [
        'a', 'an', 'the', 'i', 'me', 'my', 'need', 'want', 'find', 'looking',
        'for', 'about', 'on', 'book', 'books', 'that', 'is', 'are', 'with',
        'and', 'or', 'please', 'show', 'give', 'available', 'unavailable',
        'borrowed', 'published', 'after', 'before', 'since', 'prior', 'to', 'by',
    ];

    public function extractSearchIntent(string $query): SearchIntentData
    {
        $lower = mb_strtolower($query);

        $availability = null;
        if (preg_match('/\bavailable\b/', $lower)) {
            $availability = 'available';
        } elseif (preg_match('/\b(borrowed|unavailable|checked out|not available)\b/', $lower)) {
            $availability = 'borrowed';
        }

        $publishedAfter = null;
        if (preg_match('/\b(?:after|since)\s+(\d{4})\b/', $lower, $m)) {
            $publishedAfter = (int) $m[1];
        }

        $publishedBefore = null;
        if (preg_match('/\bbefore\s+(\d{4})\b/', $lower, $m)) {
            $publishedBefore = (int) $m[1];
        }

        $author = null;
        if (preg_match('/\bby\s+([a-z][a-z.\'\-]*(?:\s+[a-z][a-z.\'\-]*){0,3})/i', $query, $m)) {
            $author = trim($m[1]);
        }

        // Whatever's left, minus stopwords and any consumed year/author tokens,
        // becomes the keyword list.
        $words = preg_split('/[^a-z0-9\']+/', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keywords = array_values(array_filter($words, function ($word) {
            if (in_array($word, self::STOPWORDS, true)) {
                return false;
            }

            return ! preg_match('/^\d{4}$/', $word);
        }));

        return SearchIntentData::fromArray([
            'keywords' => $keywords,
            'author' => $author,
            'availability' => $availability,
            'published_after' => $publishedAfter,
            'published_before' => $publishedBefore,
        ]);
    }

    public function summarize(string $query, Collection $books): string
    {
        if ($books->isEmpty()) {
            return 'No books in the catalog matched that request.';
        }

        $count = $books->count();
        $noun = $count === 1 ? 'book' : 'books';

        return "Found {$count} {$noun} in the catalog matching the terms in your request.";
    }
}
