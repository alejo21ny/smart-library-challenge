import AvailabilityBadge from '@/Components/AvailabilityBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Book } from '@/types/models';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function CatalogShow({ book }: { book: Book }) {
    const user = usePage().props.auth.user as unknown as { role: string };

    function handleBorrow() {
        router.post(
            route('catalog.borrow', book.id),
            {},
            { preserveScroll: true },
        );
    }

    return (
        <AuthenticatedLayout>
            <Head title={book.title} />

            <Link
                href={route('catalog.index')}
                className="text-sm text-ink-muted hover:text-accent dark:text-ink-dark-muted"
            >
                ← Back to catalog
            </Link>

            <article className="mt-4 rounded-lg border border-line bg-paper-raised p-6 dark:border-line-dark dark:bg-paper-dark-raised">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="font-serif text-3xl font-semibold">
                            {book.title}
                        </h1>
                        <p className="mt-1 text-ink-muted dark:text-ink-dark-muted">
                            {book.author}
                            {book.publication_year
                                ? ` · ${book.publication_year}`
                                : ''}
                        </p>
                    </div>
                    <AvailabilityBadge availability={book.availability} />
                </div>

                {book.tags.length > 0 && (
                    <div className="mt-4 flex flex-wrap gap-1.5">
                        {book.tags.map((tag) => (
                            <span
                                key={tag.id}
                                className="rounded-full bg-line/50 px-2.5 py-0.5 text-xs text-ink-muted dark:bg-line-dark/50 dark:text-ink-dark-muted"
                            >
                                {tag.name}
                            </span>
                        ))}
                    </div>
                )}

                {book.description && (
                    <p className="mt-6 max-w-2xl leading-relaxed text-ink-muted dark:text-ink-dark-muted">
                        {book.description}
                    </p>
                )}

                <dl className="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                    {book.isbn && (
                        <div>
                            <dt className="text-ink-faint dark:text-ink-dark-faint">
                                ISBN
                            </dt>
                            <dd className="mt-0.5">{book.isbn}</dd>
                        </div>
                    )}
                    {book.category && (
                        <div>
                            <dt className="text-ink-faint dark:text-ink-dark-faint">
                                Category
                            </dt>
                            <dd className="mt-0.5">{book.category}</dd>
                        </div>
                    )}
                </dl>

                <div className="mt-8 border-t border-line pt-6 dark:border-line-dark">
                    {book.availability === 'available' ? (
                        <button
                            type="button"
                            onClick={handleBorrow}
                            className="rounded-lg bg-accent px-5 py-2.5 text-sm font-medium text-white hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent dark:bg-accent-dark dark:hover:bg-accent-dark-hover"
                        >
                            Borrow this book
                        </button>
                    ) : (
                        <p className="text-sm text-ink-muted dark:text-ink-dark-muted">
                            This book is currently borrowed
                            {user.role !== 'member' && book.active_loan?.user
                                ? ` by ${book.active_loan.user.name}`
                                : ''}
                            {book.active_loan?.due_at
                                ? `, due back ${new Date(book.active_loan.due_at).toLocaleDateString()}.`
                                : '.'}
                        </p>
                    )}
                </div>
            </article>
        </AuthenticatedLayout>
    );
}
