import EmptyState from '@/Components/EmptyState';
import Pagination, { Paginated } from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Loan } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';

function isOverdue(loan: Loan): boolean {
    return !loan.returned_at && new Date(loan.due_at) < new Date();
}

export default function MyLoansIndex({ loans }: { loans: Paginated<Loan> }) {
    function handleReturn(loan: Loan) {
        router.post(
            route('loans.return', loan.id),
            {},
            { preserveScroll: true },
        );
    }

    return (
        <AuthenticatedLayout
            header={
                <h1 className="font-serif text-2xl font-semibold">My Loans</h1>
            }
        >
            <Head title="My Loans" />

            {loans.data.length === 0 ? (
                <EmptyState
                    title="You haven't borrowed any books yet"
                    body="Browse the catalog to find something to read."
                />
            ) : (
                <ul className="flex flex-col gap-3">
                    {loans.data.map((loan) => (
                        <li
                            key={loan.id}
                            className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-paper-raised p-4 dark:border-line-dark dark:bg-paper-dark-raised"
                        >
                            <div>
                                <Link
                                    href={route('catalog.show', loan.book_id)}
                                    className="font-serif text-lg font-medium hover:text-accent dark:hover:text-accent-dark"
                                >
                                    {loan.book?.title}
                                </Link>
                                <p className="mt-1 text-sm text-ink-muted dark:text-ink-dark-muted">
                                    Borrowed{' '}
                                    {new Date(
                                        loan.borrowed_at,
                                    ).toLocaleDateString()}
                                    {loan.returned_at
                                        ? ` · Returned ${new Date(loan.returned_at).toLocaleDateString()}`
                                        : ` · Due ${new Date(loan.due_at).toLocaleDateString()}`}
                                </p>
                                {!loan.returned_at && isOverdue(loan) && (
                                    <p className="mt-1 text-xs font-medium text-danger dark:text-danger-dark">
                                        Overdue
                                    </p>
                                )}
                            </div>
                            {!loan.returned_at && (
                                <button
                                    type="button"
                                    onClick={() => handleReturn(loan)}
                                    className="rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink-muted hover:border-accent hover:text-accent dark:border-line-dark dark:text-ink-dark-muted"
                                >
                                    Return
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <Pagination pagination={loans} />
        </AuthenticatedLayout>
    );
}
