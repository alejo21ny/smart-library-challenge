import { Link } from '@inertiajs/react';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export default function Pagination({
    pagination,
}: {
    pagination: Paginated<unknown>;
}) {
    if (pagination.last_page <= 1) return null;

    return (
        <nav
            aria-label="Pagination"
            className="mt-6 flex flex-wrap items-center justify-between gap-3"
        >
            <p className="text-xs text-ink-faint dark:text-ink-dark-faint">
                Showing {pagination.from ?? 0}–{pagination.to ?? 0} of{' '}
                {pagination.total}
            </p>
            <ul className="flex flex-wrap items-center gap-1">
                {pagination.links.map((link, i) => (
                    <li key={i}>
                        {link.url ? (
                            <Link
                                href={link.url}
                                preserveScroll
                                aria-current={link.active ? 'page' : undefined}
                                className={`inline-flex min-w-[2rem] items-center justify-center rounded-md px-2.5 py-1.5 text-xs font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent ${
                                    link.active
                                        ? 'bg-ink text-paper dark:bg-ink-dark dark:text-paper-dark'
                                        : 'text-ink-muted hover:bg-line/50 dark:text-ink-dark-muted dark:hover:bg-line-dark/50'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span
                                className="inline-flex min-w-[2rem] items-center justify-center rounded-md px-2.5 py-1.5 text-xs text-ink-faint dark:text-ink-dark-faint"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        )}
                    </li>
                ))}
            </ul>
        </nav>
    );
}
