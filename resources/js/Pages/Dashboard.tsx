import EmptyState from '@/Components/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Loan, RecentActivityItem } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

interface StaffProps {
    role: 'admin' | 'librarian';
    stats: {
        totalBooks: number;
        available: number;
        borrowed: number;
        overdue: number;
    };
    recentAuditEvents: RecentActivityItem[];
}

interface MemberProps {
    role: 'member';
    stats: {
        activeLoans: number;
        overdueLoans: number;
        totalBooks: number;
        available: number;
    };
    myActiveLoans: Loan[];
}

type Props = StaffProps | MemberProps;

function StatTile({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone?: 'danger';
}) {
    return (
        <div className="rounded-lg border border-line bg-paper-raised p-4 dark:border-line-dark dark:bg-paper-dark-raised">
            <div
                className={`font-serif text-3xl font-semibold ${tone === 'danger' && value > 0 ? 'text-danger dark:text-danger-dark' : ''}`}
            >
                {value}
            </div>
            <div className="mt-1 text-sm text-ink-muted dark:text-ink-dark-muted">
                {label}
            </div>
        </div>
    );
}

export default function Dashboard(props: Props) {
    return (
        <AuthenticatedLayout
            header={
                <h1 className="font-serif text-2xl font-semibold">Dashboard</h1>
            }
        >
            <Head title="Dashboard" />

            {props.role === 'member' ? (
                <MemberDashboard {...props} />
            ) : (
                <StaffDashboard {...props} />
            )}
        </AuthenticatedLayout>
    );
}

function MemberDashboard({ stats, myActiveLoans }: MemberProps) {
    return (
        <>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatTile label="Your active loans" value={stats.activeLoans} />
                <StatTile
                    label="Overdue"
                    value={stats.overdueLoans}
                    tone="danger"
                />
                <StatTile label="Books in catalog" value={stats.totalBooks} />
                <StatTile label="Available now" value={stats.available} />
            </div>

            <div className="mt-8">
                <div className="flex items-center justify-between">
                    <h2 className="font-serif text-xl font-semibold">
                        Your active loans
                    </h2>
                    <Link
                        href={route('loans.mine')}
                        className="text-sm text-accent hover:underline dark:text-accent-dark"
                    >
                        View all
                    </Link>
                </div>
                <div className="mt-3">
                    {myActiveLoans.length === 0 ? (
                        <EmptyState
                            title="No active loans"
                            body="Browse the catalog to borrow your first book."
                        />
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {myActiveLoans.map((loan) => (
                                <li
                                    key={loan.id}
                                    className="flex items-center justify-between rounded-lg border border-line bg-paper-raised px-4 py-3 dark:border-line-dark dark:bg-paper-dark-raised"
                                >
                                    <Link
                                        href={route(
                                            'catalog.show',
                                            loan.book_id,
                                        )}
                                        className="hover:text-accent dark:hover:text-accent-dark"
                                    >
                                        {loan.book?.title}
                                    </Link>
                                    <span className="text-sm text-ink-muted dark:text-ink-dark-muted">
                                        Due{' '}
                                        {new Date(
                                            loan.due_at,
                                        ).toLocaleDateString()}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}

function StaffDashboard({ stats, recentAuditEvents }: StaffProps) {
    return (
        <>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatTile label="Total books" value={stats.totalBooks} />
                <StatTile label="Available" value={stats.available} />
                <StatTile label="Borrowed" value={stats.borrowed} />
                <StatTile label="Overdue" value={stats.overdue} tone="danger" />
            </div>

            <div className="mt-8">
                <h2 className="font-serif text-xl font-semibold">
                    Recent activity
                </h2>
                <div className="mt-3">
                    {recentAuditEvents.length === 0 ? (
                        <EmptyState title="No activity recorded yet" />
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {recentAuditEvents.map((event) => (
                                <li
                                    key={event.id}
                                    className="flex items-center justify-between gap-4 rounded-lg border border-line bg-paper-raised px-4 py-3 text-sm dark:border-line-dark dark:bg-paper-dark-raised"
                                >
                                    <span>{event.description}</span>
                                    <span className="shrink-0 text-ink-faint dark:text-ink-dark-faint">
                                        {new Date(
                                            event.created_at,
                                        ).toLocaleString()}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}
