<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Data\SearchIntentData;
use App\Models\Book;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Works with any OpenAI-Chat-Completions-compatible endpoint (OpenAI itself,
 * and any self-hosted/third-party provider that speaks the same API shape) —
 * the domain never binds to one vendor. Configure via AI_BASE_URL/AI_MODEL.
 */
class OpenAICompatibleAiProvider implements AiProviderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function extractSearchIntent(string $query): SearchIntentData
    {
        $response = $this->client()->post('/chat/completions', [
            'model' => $this->model,
            'temperature' => 0,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $this->intentSystemPrompt()],
                ['role' => 'user', 'content' => $query],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('AI provider request failed: '.$response->status());
        }

        $content = $response->json('choices.0.message.content');
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            throw new RuntimeException('AI provider returned an unparseable intent.');
        }

        return SearchIntentData::fromArray($decoded);
    }

    public function summarize(string $query, Collection $books): string
    {
        // Only real, retrieved book metadata is ever sent as context — the
        // model is explicitly instructed to use nothing else.
        $context = $books->map(fn (Book $book) => [
            'title' => $book->title,
            'author' => $book->author,
            'year' => $book->publication_year,
            'category' => $book->category,
            'availability' => $book->availability,
        ])->all();

        $response = $this->client()->post('/chat/completions', [
            'model' => $this->model,
            'temperature' => 0.3,
            'messages' => [
                ['role' => 'system', 'content' => $this->summarySystemPrompt()],
                ['role' => 'user', 'content' => json_encode([
                    'user_query' => $query,
                    'matched_books' => $context,
                ])],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('AI provider request failed: '.$response->status());
        }

        $text = $response->json('choices.0.message.content');

        return is_string($text) && trim($text) !== ''
            ? trim($text)
            : 'These results match the terms in your request.';
    }

    private function client()
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->apiKey)
            ->timeout(15)
            ->acceptJson();
    }

    private function intentSystemPrompt(): string
    {
        return <<<'PROMPT'
            You convert a library patron's natural-language request into structured
            search parameters for a real catalog database. You do not know about any
            actual books — you only extract what the user is asking for.

            Respond with ONLY a JSON object with these keys (omit or use null for
            anything not mentioned):
            - keywords: string[] (general search terms — title/subject words)
            - author: string|null
            - isbn: string|null
            - tags: string[] (genre/topic tags mentioned)
            - availability: "available" | "borrowed" | null
            - published_after: integer year | null
            - published_before: integer year | null

            Never include book titles, authors, or facts you were not given by the
            user — you are extracting intent, not answering from your own knowledge.
            PROMPT;
    }

    private function summarySystemPrompt(): string
    {
        return <<<'PROMPT'
            You explain, in one or two short sentences, why the given books matched
            a library patron's request. You will be given the user's query and a
            JSON list of the ACTUAL matched books (title, author, year, category,
            availability) — this list is the complete and only truth available to
            you. Reference ONLY books and facts present in that list. Never mention
            a book, author, or detail that is not explicitly in the provided list.
            If the list is empty, say so plainly.
            PROMPT;
    }
}
