import EmptyState from '@/Components/EmptyState';
import Pagination, { Paginated } from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Loan } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface MemberOption {
    id: number;
    name: string;
    email: string;
}

interface BookOption {
    id: number;
    title: string;
    author: string;
}

interface Props {
    loans: Paginated<Loan>;
    filters: { status?: string };
    members: MemberOption[];
    availableBooks: BookOption[];
}

function isOverdue(loan: Loan): boolean {
    return !loan.returned_at && new Date(loan.due_at) < new Date();
}

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'returned', label: 'Returned' },
];

export default function AdminLoansIndex({
    loans,
    filters,
    members,
    availableBooks,
}: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        book_id: '',
        user_id: '',
    });

    function submitBorrow(e: FormEvent) {
        e.preventDefault();
        post(route('admin.loans.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    }

    return (
        <AuthenticatedLayout
            header={
                <h1 className="font-serif text-2xl font-semibold">
                    Circulation
                </h1>
            }
        >
            <Head title="Circulation" />

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <div>
                    <div
                        role="tablist"
                        aria-label="Loan status"
                        className="flex gap-1"
                    >
                        {STATUS_TABS.map((tab) => (
                            <button
                                key={tab.value}
                                role="tab"
                                aria-selected={
                                    filters.status === tab.value ||
                                    (!filters.status && tab.value === '')
                                }
                                onClick={() =>
                                    router.get(route('admin.loans.index'), {
                                        status: tab.value || undefined,
                                    })
                                }
                                className={`rounded-md px-3 py-1.5 text-sm font-medium ${
                                    (filters.status ?? '') === tab.value
                                        ? 'bg-ink text-paper dark:bg-ink-dark dark:text-paper-dark'
                                        : 'text-ink-muted hover:bg-line/50 dark:text-ink-dark-muted'
                                }`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    <div className="mt-4">
                        {loans.data.length === 0 ? (
                            <EmptyState title="No loans in this view" />
                        ) : (
                            <div className="overflow-x-auto rounded-lg border border-line dark:border-line-dark">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-line bg-paper-raised text-xs uppercase tracking-wide text-ink-faint dark:border-line-dark dark:bg-paper-dark-raised dark:text-ink-dark-faint">
                                            <th className="px-4 py-3 font-medium">
                                                Book
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Borrower
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Due
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {loans.data.map((loan) => (
                                            <tr
                                                key={loan.id}
                                                className="border-b border-line last:border-0 dark:border-line-dark"
                                            >
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={route(
                                                            'catalog.show',
                                                            loan.book_id,
                                                        )}
                                                        className="hover:text-accent dark:hover:text-accent-dark"
                                                    >
                                                        {loan.book?.title}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3 text-ink-muted dark:text-ink-dark-muted">
                                                    {loan.user?.name}
                                                </td>
                                                <td className="px-4 py-3 text-ink-muted dark:text-ink-dark-muted">
                                                    {new Date(
                                                        loan.due_at,
                                                    ).toLocaleDateString()}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {loan.returned_at ? (
                                                        <span className="text-ink-faint dark:text-ink-dark-faint">
                                                            Returned
                                                        </span>
                                                    ) : isOverdue(loan) ? (
                                                        <span className="font-medium text-danger dark:text-danger-dark">
                                                            Overdue
                                                        </span>
                                                    ) : (
                                                        <span className="text-success dark:text-success-dark">
                                                            Active
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <Pagination pagination={loans} />
                    </div>
                </div>

                <div className="h-fit rounded-lg border border-line bg-paper-raised p-4 dark:border-line-dark dark:bg-paper-dark-raised">
                    <h2 className="font-serif text-lg font-medium">
                        Check out a book
                    </h2>
                    <p className="mt-1 text-xs text-ink-faint dark:text-ink-dark-faint">
                        Borrow on behalf of a member (e.g. in-person checkout).
                    </p>
                    <form
                        onSubmit={submitBorrow}
                        className="mt-3 flex flex-col gap-3"
                    >
                        <div>
                            <label
                                htmlFor="checkout-book"
                                className="block text-xs font-medium text-ink-muted dark:text-ink-dark-muted"
                            >
                                Book
                            </label>
                            <select
                                id="checkout-book"
                                value={data.book_id}
                                onChange={(e) =>
                                    setData('book_id', e.target.value)
                                }
                                className="input mt-1"
                                required
                            >
                                <option value="">
                                    Select an available book…
                                </option>
                                {availableBooks.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {b.title} — {b.author}
                                    </option>
                                ))}
                            </select>
                            {errors.book_id && (
                                <p className="mt-1 text-xs text-danger dark:text-danger-dark">
                                    {errors.book_id}
                                </p>
                            )}
                        </div>
                        <div>
                            <label
                                htmlFor="checkout-member"
                                className="block text-xs font-medium text-ink-muted dark:text-ink-dark-muted"
                            >
                                Member
                            </label>
                            <select
                                id="checkout-member"
                                value={data.user_id}
                                onChange={(e) =>
                                    setData('user_id', e.target.value)
                                }
                                className="input mt-1"
                                required
                            >
                                <option value="">Select a member…</option>
                                {members.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name} ({m.email})
                                    </option>
                                ))}
                            </select>
                            {errors.user_id && (
                                <p className="mt-1 text-xs text-danger dark:text-danger-dark">
                                    {errors.user_id}
                                </p>
                            )}
                        </div>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-hover disabled:opacity-50 dark:bg-accent-dark dark:hover:bg-accent-dark-hover"
                        >
                            Check out
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
