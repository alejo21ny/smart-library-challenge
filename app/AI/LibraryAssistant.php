<?php

namespace App\AI;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Data\BookMatch;
use App\AI\Data\CatalogSearchResult;
use App\AI\Data\SearchIntentData;
use App\AI\Intent\ActionClassifier;
use App\AI\Intent\AssistantAction;
use App\AI\Providers\NullAiProvider;
use App\AI\Tools\LibraryTools;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates: user message -> deterministic action/tool selection -> a
 * real, read-only query against the catalog/loan tables via LibraryTools ->
 * real rows -> a grounded response. An AI provider, when configured, is
 * consulted for two things only: turning free text into SearchIntentData
 * (structured query parameters, never book data) and improving the wording
 * of a response that is already grounded in real retrieved rows. Either
 * call failing degrades to the deterministic path — the user never sees a
 * raw provider exception. See ARCHITECTURE.md §11.
 */
class LibraryAssistant
{
    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly LibraryTools $tools,
    ) {}

    /**
     * @return array{
     *     action: string,
     *     message: string,
     *     books: Collection<int, Book>,
     *     loans: Collection<int, Loan>,
     *     summary: ?array{total: int, available: int, borrowed: int, overdue: int},
     *     intent: ?SearchIntentData,
     *     whyMatched: string[],
     *     suggestion: ?Book,
     *     usedFuzzy: bool,
     *     degraded: bool,
     * }
     */
    public function query(string $rawQuery, User $user): array
    {
        $action = ActionClassifier::classify($rawQuery, $user->isStaff());

        return match ($action) {
            AssistantAction::GetMyLoans => $this->handleMyLoans($user),
            AssistantAction::GetLibrarySummary => $user->isStaff()
                ? $this->handleLibrarySummary()
                : $this->handleSearch($rawQuery), // non-staff can never reach summary data, even if misclassified
            AssistantAction::CheckAvailability => $this->handleCheckAvailability($rawQuery),
            AssistantAction::SearchCatalog => $this->handleSearch($rawQuery),
        };
    }

    private function handleSearch(string $rawQuery): array
    {
        [$intent, $degraded] = $this->extractIntent($rawQuery);
        $result = $this->tools->searchCatalog($intent);

        $message = $this->summarizeGracefully($rawQuery, $result->books)
            ?? $this->deterministicSearchMessage($result, $intent);

        return $this->response(
            action: AssistantAction::SearchCatalog,
            message: $message,
            books: $result->books,
            intent: $intent,
            whyMatched: $result->books->isNotEmpty() ? $this->whyMatched($intent, $result->usedFuzzy) : [],
            suggestion: $result->suggestion,
            usedFuzzy: $result->usedFuzzy,
            degraded: $degraded,
        );
    }

    private function handleCheckAvailability(string $rawQuery): array
    {
        $subject = ActionClassifier::extractAvailabilitySubject($rawQuery);
        $match = $this->tools->checkAvailability($subject);

        $message = $this->availabilityMessage($match);
        $books = $match->confident && $match->book ? collect([$match->book]) : collect();

        return $this->response(
            action: AssistantAction::CheckAvailability,
            message: $message,
            books: $books,
            intent: null,
            whyMatched: $books->isNotEmpty() ? ['currently '.$match->book->availability] : [],
            suggestion: (! $match->confident) ? $match->book : null,
            usedFuzzy: true,
            degraded: false,
        );
    }

    private function handleMyLoans(User $user): array
    {
        $loans = $this->tools->getMyLoans($user);
        $active = $loans->filter(fn ($loan) => $loan->isActive());

        $message = $active->isEmpty()
            ? 'You have no books currently borrowed.'
            : ($active->count() === 1
                ? 'You currently have 1 book borrowed: '.$active->first()->book->title.'.'
                : 'You currently have '.$active->count().' books borrowed.');

        return $this->response(
            action: AssistantAction::GetMyLoans,
            message: $message,
            loans: $loans,
        );
    }

    private function handleLibrarySummary(): array
    {
        $summary = $this->tools->getLibrarySummary();

        $message = sprintf(
            '%d books in the catalog — %d available, %d borrowed, %d overdue.',
            $summary['total'],
            $summary['available'],
            $summary['borrowed'],
            $summary['overdue'],
        );

        return $this->response(
            action: AssistantAction::GetLibrarySummary,
            message: $message,
            summary: $summary,
        );
    }

    /** @return array{0: SearchIntentData, 1: bool} */
    private function extractIntent(string $query): array
    {
        try {
            return [$this->provider->extractSearchIntent($query), false];
        } catch (Throwable $e) {
            Log::warning('Library Assistant: intent extraction failed, falling back to keyword search.', [
                'error' => $e->getMessage(),
            ]);

            return [(new NullAiProvider)->extractSearchIntent($query), true];
        }
    }

    private function summarizeGracefully(string $query, Collection $books): ?string
    {
        if ($books->isEmpty() || $this->provider instanceof NullAiProvider) {
            return null;
        }

        try {
            return $this->provider->summarize($query, $books);
        } catch (Throwable $e) {
            Log::warning('Library Assistant: summary generation failed.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function deterministicSearchMessage(CatalogSearchResult $result, SearchIntentData $intent): string
    {
        if ($result->books->isEmpty()) {
            if ($result->suggestion) {
                return sprintf('No exact match — did you mean "%s" by %s?', $result->suggestion->title, $result->suggestion->author);
            }

            return $intent->isEmpty()
                ? "I couldn't quite understand that — try mentioning a title, author, tag, or a year."
                : 'No books in the catalog matched that request.';
        }

        $count = $result->books->count();
        $noun = $count === 1 ? 'book' : 'books';

        return $result->usedFuzzy
            ? "Found {$count} {$noun} closest to what you searched for."
            : "Found {$count} {$noun} matching your request.";
    }

    private function availabilityMessage(BookMatch $match): string
    {
        if (! $match->book) {
            return "I couldn't find a book matching that in the catalog.";
        }

        if (! $match->confident) {
            return sprintf(
                'No exact match — did you mean "%s"? It is currently %s.',
                $match->book->title,
                $match->book->availability,
            );
        }

        return $match->book->availability === 'available'
            ? sprintf('Yes, "%s" is available.', $match->book->title)
            : sprintf('"%s" is currently borrowed.', $match->book->title);
    }

    /** @return string[] */
    private function whyMatched(SearchIntentData $intent, bool $usedFuzzy): array
    {
        $reasons = [];

        if ($usedFuzzy) {
            $reasons[] = 'closest title/author match to your wording';
        } elseif ($intent->keywords !== []) {
            $reasons[] = 'matches "'.implode(' ', $intent->keywords).'" in title, author, or description';
        }

        if ($intent->author) {
            $reasons[] = 'author matches "'.$intent->author.'"';
        }

        if ($intent->tags !== []) {
            $reasons[] = 'tagged '.implode(', ', $intent->tags);
        }

        if ($intent->publishedAfter) {
            $reasons[] = 'published after '.$intent->publishedAfter;
        }

        if ($intent->publishedBefore) {
            $reasons[] = 'published before '.$intent->publishedBefore;
        }

        if ($intent->availability === 'available') {
            $reasons[] = 'currently available';
        } elseif ($intent->availability === 'borrowed') {
            $reasons[] = 'currently borrowed';
        }

        return $reasons;
    }

    /**
     * @param  Collection<int, Book>|null  $books
     * @param  Collection<int, Loan>|null  $loans
     * @param  array{total: int, available: int, borrowed: int, overdue: int}|null  $summary
     * @param  string[]  $whyMatched
     * @return array<string, mixed>
     */
    private function response(
        AssistantAction $action,
        string $message,
        ?Collection $books = null,
        ?Collection $loans = null,
        ?array $summary = null,
        ?SearchIntentData $intent = null,
        array $whyMatched = [],
        ?Book $suggestion = null,
        bool $usedFuzzy = false,
        bool $degraded = false,
    ): array {
        return [
            'action' => $action->value,
            'message' => $message,
            'books' => $books ?? collect(),
            'loans' => $loans ?? collect(),
            'summary' => $summary,
            'intent' => $intent,
            'whyMatched' => $whyMatched,
            'suggestion' => $suggestion,
            'usedFuzzy' => $usedFuzzy,
            'degraded' => $degraded,
        ];
    }
}
