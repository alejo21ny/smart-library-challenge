<?php

namespace App\AI\Data;

/**
 * Structured search intent — the ONLY thing an AI provider is allowed to
 * produce about a catalog query. It is parameters for a real database query,
 * never book data itself. See ARCHITECTURE.md §11 for the anti-hallucination
 * guarantees this class exists to enforce.
 */
final readonly class SearchIntentData
{
    /**
     * @param  string[]  $keywords
     * @param  string[]  $tags
     */
    public function __construct(
        public array $keywords = [],
        public ?string $author = null,
        public ?string $isbn = null,
        public array $tags = [],
        public ?string $availability = null, // 'available' | 'borrowed' | null
        public ?int $publishedBefore = null,
        public ?int $publishedAfter = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $availability = $data['availability'] ?? null;
        if (! in_array($availability, ['available', 'borrowed'], true)) {
            $availability = null;
        }

        return new self(
            keywords: self::toStringList($data['keywords'] ?? []),
            author: self::toNullableString($data['author'] ?? null),
            isbn: self::toNullableString($data['isbn'] ?? null),
            tags: self::toStringList($data['tags'] ?? []),
            availability: $availability,
            publishedBefore: self::toNullableYear($data['published_before'] ?? null),
            publishedAfter: self::toNullableYear($data['published_after'] ?? null),
        );
    }

    public function isEmpty(): bool
    {
        return $this->keywords === []
            && $this->author === null
            && $this->isbn === null
            && $this->tags === []
            && $this->availability === null
            && $this->publishedBefore === null
            && $this->publishedAfter === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'keywords' => $this->keywords,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'tags' => $this->tags,
            'availability' => $this->availability,
            'published_before' => $this->publishedBefore,
            'published_after' => $this->publishedAfter,
        ];
    }

    /**
     * @return string[]
     */
    private static function toStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($v) => is_string($v) ? trim($v) : null,
            $value,
        )));
    }

    private static function toNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function toNullableYear(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $year = (int) $value;

        return ($year >= 1450 && $year <= (int) date('Y') + 1) ? $year : null;
    }
}
