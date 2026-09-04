import AvailabilityBadge from '@/Components/AvailabilityBadge';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import Pagination, { Paginated } from '@/Components/Pagination';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Book } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    books: Paginated<Book>;
    filters: { q?: string; availability?: string };
}

export default function AdminBooksIndex({ books, filters }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const [pendingDelete, setPendingDelete] = useState<Book | null>(null);

    function search() {
        router.get(
            route('admin.books.index'),
            { ...filters, q },
            { preserveState: true, replace: true },
        );
    }

    function confirmDelete() {
        if (!pendingDelete) return;
        router.delete(route('admin.books.destroy', pendingDelete.id), {
            preserveScroll: true,
            onFinish: () => setPendingDelete(null),
        });
    }

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="font-serif text-2xl font-semibold">
                        Manage Books
                    </h1>
                    <Link
                        href={route('admin.books.create')}
                        className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-hover dark:bg-accent-dark dark:hover:bg-accent-dark-hover"
                    >
                        + Add book
                    </Link>
                </div>
            }
        >
            <Head title="Manage Books" />

            <div className="flex gap-2">
                <input
                    type="search"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && search()}
                    placeholder="Search the catalog…"
                    className="w-full max-w-sm rounded-lg border-line bg-paper-raised text-sm focus:border-accent focus:ring-accent dark:border-line-dark dark:bg-paper-dark-raised dark:text-ink-dark"
                />
                <button
                    type="button"
                    onClick={search}
                    className="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink-muted hover:border-accent hover:text-accent dark:border-line-dark dark:text-ink-dark-muted"
                >
                    Search
                </button>
            </div>

            <div className="mt-4">
                {books.data.length === 0 ? (
                    <EmptyState
                        title="No books found"
                        body="Add the first book to get the catalog started."
                    />
                ) : (
                    <>
                        {/* Desktop/tablet: full table. */}
                        <div className="hidden overflow-x-auto rounded-lg border border-line dark:border-line-dark sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-line bg-paper-raised text-xs uppercase tracking-wide text-ink-faint dark:border-line-dark dark:bg-paper-dark-raised dark:text-ink-dark-faint">
                                        <th className="px-4 py-3 font-medium">
                                            Title
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Author
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {books.data.map((book) => (
                                        <tr
                                            key={book.id}
                                            className="border-b border-line last:border-0 dark:border-line-dark"
                                        >
                                            <td className="px-4 py-3">
                                                {book.title}
                                            </td>
                                            <td className="px-4 py-3 text-ink-muted dark:text-ink-dark-muted">
                                                {book.author}
                                            </td>
                                            <td className="px-4 py-3">
                                                <AvailabilityBadge
                                                    availability={
                                                        book.availability
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex gap-3">
                                                    <Link
                                                        href={route(
                                                            'admin.books.edit',
                                                            book.id,
                                                        )}
                                                        className="text-accent hover:underline dark:text-accent-dark"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setPendingDelete(
                                                                book,
                                                            )
                                                        }
                                                        className="text-danger hover:underline dark:text-danger-dark"
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Mobile: cards, so Edit/Delete stay visible without horizontal scrolling. */}
                        <ul className="flex flex-col gap-3 sm:hidden">
                            {books.data.map((book) => (
                                <li
                                    key={book.id}
                                    className="rounded-lg border border-line bg-paper-raised p-4 dark:border-line-dark dark:bg-paper-dark-raised"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="font-serif font-medium leading-snug">
                                                {book.title}
                                            </p>
                                            <p className="mt-0.5 text-sm text-ink-muted dark:text-ink-dark-muted">
                                                {book.author}
                                            </p>
                                        </div>
                                        <AvailabilityBadge
                                            availability={book.availability}
                                        />
                                    </div>
                                    <div className="mt-3 flex gap-4 border-t border-line pt-3 text-sm dark:border-line-dark">
                                        <Link
                                            href={route(
                                                'admin.books.edit',
                                                book.id,
                                            )}
                                            className="font-medium text-accent hover:underline dark:text-accent-dark"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setPendingDelete(book)
                                            }
                                            className="font-medium text-danger hover:underline dark:text-danger-dark"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
                <Pagination pagination={books} />
            </div>

            <Modal
                show={pendingDelete !== null}
                onClose={() => setPendingDelete(null)}
            >
                <div className="p-6">
                    <h2 className="font-serif text-lg font-semibold">
                        Remove this book?
                    </h2>
                    <p className="mt-2 text-sm text-ink-muted dark:text-ink-dark-muted">
                        {pendingDelete && (
                            <>
                                <span className="font-medium text-ink dark:text-ink-dark">
                                    &ldquo;{pendingDelete.title}&rdquo;
                                </span>{' '}
                                will be permanently removed from the catalog.
                                This cannot be undone.
                            </>
                        )}
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={() => setPendingDelete(null)}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            onClick={confirmDelete}
                            className="!bg-danger hover:!bg-danger/90 dark:!bg-danger-dark"
                        >
                            Remove book
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
