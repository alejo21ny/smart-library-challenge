import AvailabilityBadge from '@/Components/AvailabilityBadge';
import EmptyState from '@/Components/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Book, Loan } from '@/types/models';
import { Head, Link, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Intent {
    keywords: string[];
    author: string | null;
    isbn: string | null;
    tags: string[];
    availability: string | null;
    published_before: number | null;
    published_after: number | null;
}

type AssistantAction =
    | 'search_catalog'
    | 'check_availability'
    | 'get_my_loans'
    | 'get_library_summary';

interface LibrarySummary {
    total: number;
    available: number;
    borrowed: number;
    overdue: number;
}

interface AssistantResponse {
    action: AssistantAction;
    message: string;
    books: Book[];
    loans: Loan[];
    summary: LibrarySummary | null;
    intent: Intent | null;
    whyMatched: string[];
    suggestion: Book | null;
    usedFuzzy: boolean;
    degraded: boolean;
}

interface Turn {
    id: number;
    query: string;
    response: AssistantResponse | null;
    error: string | null;
    loading: boolean;
}

const MEMBER_EXAMPLES = [
    'Find available Laravel or clean architecture books published after 2020.',
    'Do you have Clean Architecture available?',
    'What books do I currently have borrowed?',
    'Show me beginner-friendly PHP books.',
];

const STAFF_EXAMPLE = 'Give me a quick circulation summary.';

let turnId = 0;

export default function AssistantIndex() {
    const { auth } = usePage<PageProps>().props;
    const isStaff =
        auth.user.role === 'admin' || auth.user.role === 'librarian';
    const examples = isStaff
        ? [...MEMBER_EXAMPLES, STAFF_EXAMPLE]
        : MEMBER_EXAMPLES;

    const [query, setQuery] = useState('');
    const [turns, setTurns] = useState<Turn[]>([]);

    async function runQuery(q: string) {
        const id = ++turnId;
        setTurns((prev) => [
            ...prev,
            { id, query: q, response: null, error: null, loading: true },
        ]);
        setQuery('');

        try {
            const response = await window.axios.post<AssistantResponse>(
                route('assistant.query'),
                { query: q },
            );
            setTurns((prev) =>
                prev.map((t) =>
                    t.id === id
                        ? { ...t, response: response.data, loading: false }
                        : t,
                ),
            );
        } catch {
            setTurns((prev) =>
                prev.map((t) =>
                    t.id === id
                        ? {
                              ...t,
                              loading: false,
                              error: 'Something went wrong asking the assistant. Try again, or search the catalog directly.',
                          }
                        : t,
                ),
            );
        }
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        if (query.trim().length < 2) return;
        runQuery(query);
    }

    return (
        <AuthenticatedLayout
            header={
                <h1 className="font-serif text-2xl font-semibold">
                    Library Assistant
                </h1>
            }
        >
            <Head title="Library Assistant" />

            <p className="max-w-2xl text-sm text-ink-muted dark:text-ink-dark-muted">
                Ask about the catalog, your loans, or availability in plain
                language. The assistant only ever shows real records from this
                library — it never makes anything up.
            </p>

            <div className="mt-6 flex flex-col gap-6">
                {turns.map((turn) => (
                    <AssistantTurn
                        key={turn.id}
                        turn={turn}
                        onFollowUp={runQuery}
                    />
                ))}
            </div>

            <form
                onSubmit={handleSubmit}
                className="mt-6 flex flex-col gap-2 sm:flex-row"
            >
                <label htmlFor="assistant-query" className="sr-only">
                    Ask the Library Assistant
                </label>
                <input
                    id="assistant-query"
                    type="text"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="e.g. Find available Laravel books published after 2020"
                    className="input"
                />
                <button
                    type="submit"
                    disabled={turns.some((t) => t.loading)}
                    className="shrink-0 rounded-lg bg-accent px-5 py-2.5 text-sm font-medium text-white hover:bg-accent-hover disabled:opacity-50 dark:bg-accent-dark dark:hover:bg-accent-dark-hover"
                >
                    Ask
                </button>
            </form>

            <div className="mt-3 flex flex-wrap gap-2">
                {examples.map((ex) => (
                    <button
                        key={ex}
                        type="button"
                        onClick={() => runQuery(ex)}
                        className="rounded-full border border-line px-3 py-1 text-xs text-ink-muted hover:border-accent hover:text-accent dark:border-line-dark dark:text-ink-dark-muted"
                    >
                        {ex}
                    </button>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}

function AssistantTurn({
    turn,
    onFollowUp,
}: {
    turn: Turn;
    onFollowUp: (query: string) => void;
}) {
    const { response } = turn;

    return (
        <div className="border-l-2 border-line pl-4 dark:border-line-dark">
            <p className="text-sm font-medium text-ink-muted dark:text-ink-dark-muted">
                {turn.query}
            </p>

            {turn.loading && (
                <p className="mt-2 text-sm text-ink-faint dark:text-ink-dark-faint">
                    Thinking…
                </p>
            )}

            {turn.error && (
                <p className="mt-2 text-sm text-danger dark:text-danger-dark">
                    {turn.error}
                </p>
            )}

            {response && (
                <div className="mt-2">
                    {response.degraded && (
                        <p className="mb-2 text-xs text-ink-faint dark:text-ink-dark-faint">
                            No AI provider is configured — using keyword-based
                            search instead of full natural-language
                            understanding. Results below are still real catalog
                            matches.
                        </p>
                    )}

                    <p className="text-xs font-semibold uppercase tracking-wide text-ink-faint dark:text-ink-dark-faint">
                        Assistant response
                    </p>
                    <p className="mt-1 text-sm">{response.message}</p>

                    {response.suggestion && response.books.length === 0 && (
                        <button
                            type="button"
                            onClick={() =>
                                onFollowUp(response.suggestion!.title)
                            }
                            className="mt-2 rounded-full border border-accent/40 px-3 py-1 text-xs text-accent hover:bg-accent/5 dark:border-accent-dark/40 dark:text-accent-dark"
                        >
                            Search for &ldquo;{response.suggestion.title}&rdquo;
                            instead
                        </button>
                    )}

                    {response.action === 'search_catalog' && (
                        <IntentSummary intent={response.intent} />
                    )}

                    {response.whyMatched.length > 0 && (
                        <div className="mb-3 mt-2 rounded-lg border border-accent/30 bg-accent/5 px-4 py-3 text-sm dark:border-accent-dark/30 dark:bg-accent-dark/5">
                            <p className="text-xs font-semibold uppercase tracking-wide text-accent dark:text-accent-dark">
                                Why this matched
                            </p>
                            <ul className="mt-1 list-inside list-disc text-ink-muted dark:text-ink-dark-muted">
                                {response.whyMatched.map((reason) => (
                                    <li key={reason}>{reason}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {response.summary && (
                        <SummaryCards summary={response.summary} />
                    )}

                    {response.loans.length > 0 && (
                        <LoanCards loans={response.loans} />
                    )}

                    {response.action === 'get_my_loans' &&
                        response.loans.length === 0 && (
                            <EmptyState
                                title="No loans yet"
                                body="Browse the catalog to borrow your first book."
                            />
                        )}

                    {response.books.length > 0 && (
                        <BookCards books={response.books} />
                    )}
                </div>
            )}
        </div>
    );
}

function BookCards({ books }: { books: Book[] }) {
    return (
        <ul className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {books.map((book) => (
                <li key={book.id}>
                    <Link
                        href={route('catalog.show', book.id)}
                        className="flex h-full flex-col rounded-lg border border-line bg-paper-raised p-4 hover:border-accent dark:border-line-dark dark:bg-paper-dark-raised"
                    >
                        <div className="flex items-start justify-between gap-2">
                            <h3 className="font-serif text-base font-medium leading-snug">
                                {book.title}
                            </h3>
                            <AvailabilityBadge
                                availability={book.availability}
                            />
                        </div>
                        <p className="mt-1 text-sm text-ink-muted dark:text-ink-dark-muted">
                            {book.author}
                            {book.publication_year
                                ? ` · ${book.publication_year}`
                                : ''}
                        </p>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

function LoanCards({ loans }: { loans: Loan[] }) {
    return (
        <ul className="mt-3 flex flex-col gap-2">
            {loans.map((loan) => (
                <li
                    key={loan.id}
                    className="flex items-center justify-between rounded-lg border border-line bg-paper-raised px-4 py-3 text-sm dark:border-line-dark dark:bg-paper-dark-raised"
                >
                    <Link
                        href={route('catalog.show', loan.book_id)}
                        className="hover:text-accent dark:hover:text-accent-dark"
                    >
                        {loan.book?.title}
                    </Link>
                    <span className="text-ink-muted dark:text-ink-dark-muted">
                        {loan.returned_at
                            ? `Returned ${new Date(loan.returned_at).toLocaleDateString()}`
                            : `Due ${new Date(loan.due_at).toLocaleDateString()}`}
                    </span>
                </li>
            ))}
        </ul>
    );
}

function SummaryCards({ summary }: { summary: LibrarySummary }) {
    const tiles: { label: string; value: number }[] = [
        { label: 'Total books', value: summary.total },
        { label: 'Available', value: summary.available },
        { label: 'Borrowed', value: summary.borrowed },
        { label: 'Overdue', value: summary.overdue },
    ];

    return (
        <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            {tiles.map((tile) => (
                <div
                    key={tile.label}
                    className="rounded-lg border border-line bg-paper-raised p-3 dark:border-line-dark dark:bg-paper-dark-raised"
                >
                    <div className="font-serif text-2xl font-semibold">
                        {tile.value}
                    </div>
                    <div className="mt-0.5 text-xs text-ink-muted dark:text-ink-dark-muted">
                        {tile.label}
                    </div>
                </div>
            ))}
        </div>
    );
}

function IntentSummary({ intent }: { intent: Intent | null }) {
    if (!intent) return null;

    const parts: string[] = [];
    if (intent.keywords.length)
        parts.push(`keywords: ${intent.keywords.join(', ')}`);
    if (intent.author) parts.push(`author: ${intent.author}`);
    if (intent.isbn) parts.push(`ISBN: ${intent.isbn}`);
    if (intent.tags.length) parts.push(`tags: ${intent.tags.join(', ')}`);
    if (intent.availability) parts.push(`availability: ${intent.availability}`);
    if (intent.published_after) parts.push(`after ${intent.published_after}`);
    if (intent.published_before)
        parts.push(`before ${intent.published_before}`);

    if (parts.length === 0) return null;

    return (
        <p className="mt-1 text-xs text-ink-faint dark:text-ink-dark-faint">
            Understood as — {parts.join(' · ')}
        </p>
    );
}
