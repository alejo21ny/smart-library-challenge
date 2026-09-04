import AvailabilityBadge from '@/Components/AvailabilityBadge';
import EmptyState from '@/Components/EmptyState';
import Pagination, { Paginated } from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Book } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Filters {
    q?: string;
    category?: string;
    tag?: string;
    isbn?: string;
    author?: string;
    availability?: string;
    sort?: string;
    dir?: string;
}

interface Props {
    books: Paginated<Book>;
    filters: Filters;
    categories: string[];
    tags: string[];
}

export default function CatalogIndex({
    books,
    filters,
    categories,
    tags,
}: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const [showFilters, setShowFilters] = useState(
        Boolean(
            filters.category ||
            filters.tag ||
            filters.isbn ||
            filters.author ||
            filters.availability,
        ),
    );

    function applyFilters(overrides: Partial<Filters> = {}) {
        router.get(
            route('catalog.index'),
            { ...filters, q, ...overrides },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function handleSearchSubmit(e: FormEvent) {
        e.preventDefault();
        applyFilters();
    }

    function updateFilter(key: keyof Filters, value: string) {
        applyFilters({ [key]: value || undefined });
    }

    return (
        <AuthenticatedLayout
            header={
                <h1 className="font-serif text-2xl font-semibold">Catalog</h1>
            }
        >
            <Head title="Catalog" />

            <form
                onSubmit={handleSearchSubmit}
                className="flex flex-col gap-3 sm:flex-row sm:items-center"
            >
                <label htmlFor="catalog-search" className="sr-only">
                    Search books by title, author, or keyword
                </label>
                <input
                    id="catalog-search"
                    type="search"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Search by title, author, or keyword…"
                    className="w-full rounded-lg border-line bg-paper-raised text-sm focus:border-accent focus:ring-accent dark:border-line-dark dark:bg-paper-dark-raised dark:text-ink-dark"
                />
                <div className="flex gap-2">
                    <button
                        type="submit"
                        className="rounded-lg bg-ink px-4 py-2 text-sm font-medium text-paper hover:bg-ink/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent dark:bg-ink-dark dark:text-paper-dark"
                    >
                        Search
                    </button>
                    <button
                        type="button"
                        onClick={() => setShowFilters((v) => !v)}
                        aria-expanded={showFilters}
                        className="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink-muted hover:border-accent hover:text-accent dark:border-line-dark dark:text-ink-dark-muted"
                    >
                        Filters
                    </button>
                </div>
            </form>

            {showFilters && (
                <div className="mt-4 grid gap-3 rounded-lg border border-line bg-paper-raised p-4 dark:border-line-dark dark:bg-paper-dark-raised sm:grid-cols-2 lg:grid-cols-5">
                    <FilterSelect
                        label="Category"
                        value={filters.category ?? ''}
                        onChange={(v) => updateFilter('category', v)}
                        options={categories.map((c) => ({
                            value: c,
                            label: c,
                        }))}
                    />
                    <FilterSelect
                        label="Tag"
                        value={filters.tag ?? ''}
                        onChange={(v) => updateFilter('tag', v)}
                        options={tags.map((t) => ({ value: t, label: t }))}
                    />
                    <FilterSelect
                        label="Availability"
                        value={filters.availability ?? ''}
                        onChange={(v) => updateFilter('availability', v)}
                        options={[
                            { value: 'available', label: 'Available' },
                            { value: 'borrowed', label: 'Borrowed' },
                        ]}
                    />
                    <FilterText
                        label="Author"
                        value={filters.author ?? ''}
                        onBlur={(v) => updateFilter('author', v)}
                    />
                    <FilterText
                        label="ISBN"
                        value={filters.isbn ?? ''}
                        onBlur={(v) => updateFilter('isbn', v)}
                    />
                    <FilterSelect
                        label="Sort by"
                        value={filters.sort ?? 'title'}
                        onChange={(v) => updateFilter('sort', v)}
                        options={[
                            { value: 'title', label: 'Title' },
                            { value: 'author', label: 'Author' },
                            {
                                value: 'publication_year',
                                label: 'Publication year',
                            },
                            { value: 'created_at', label: 'Recently added' },
                        ]}
                    />
                    <div className="flex items-end">
                        <button
                            type="button"
                            onClick={() => router.get(route('catalog.index'))}
                            className="text-sm text-ink-muted underline underline-offset-2 hover:text-accent dark:text-ink-dark-muted"
                        >
                            Clear all filters
                        </button>
                    </div>
                </div>
            )}

            <div className="mt-6">
                {books.data.length === 0 ? (
                    <EmptyState
                        title="No books match your search"
                        body="Try a different keyword, or clear your filters."
                    />
                ) : (
                    <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {books.data.map((book) => (
                            <li key={book.id}>
                                <Link
                                    href={route('catalog.show', book.id)}
                                    className="flex h-full flex-col rounded-lg border border-line bg-paper-raised p-4 transition-colors hover:border-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent dark:border-line-dark dark:bg-paper-dark-raised"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <h2 className="font-serif text-lg font-medium leading-snug">
                                            {book.title}
                                        </h2>
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
                                    {book.category && (
                                        <p className="mt-2 text-xs uppercase tracking-wide text-ink-faint dark:text-ink-dark-faint">
                                            {book.category}
                                        </p>
                                    )}
                                    {book.tags.length > 0 && (
                                        <div className="mt-3 flex flex-wrap gap-1">
                                            {book.tags
                                                .slice(0, 4)
                                                .map((tag) => (
                                                    <span
                                                        key={tag.id}
                                                        className="rounded-full bg-line/50 px-2 py-0.5 text-[11px] text-ink-muted dark:bg-line-dark/50 dark:text-ink-dark-muted"
                                                    >
                                                        {tag.name}
                                                    </span>
                                                ))}
                                        </div>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination pagination={books} />
            </div>
        </AuthenticatedLayout>
    );
}

function FilterSelect({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
}) {
    const id = `filter-${label.toLowerCase().replace(/\s+/g, '-')}`;

    return (
        <div>
            <label
                htmlFor={id}
                className="block text-xs font-medium text-ink-muted dark:text-ink-dark-muted"
            >
                {label}
            </label>
            <select
                id={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="mt-1 w-full rounded-md border-line bg-paper text-sm focus:border-accent focus:ring-accent dark:border-line-dark dark:bg-paper-dark dark:text-ink-dark"
            >
                <option value="">Any</option>
                {options.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                        {opt.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

function FilterText({
    label,
    value,
    onBlur,
}: {
    label: string;
    value: string;
    onBlur: (value: string) => void;
}) {
    const [local, setLocal] = useState(value);
    const id = `filter-${label.toLowerCase()}`;

    return (
        <div>
            <label
                htmlFor={id}
                className="block text-xs font-medium text-ink-muted dark:text-ink-dark-muted"
            >
                {label}
            </label>
            <input
                id={id}
                type="text"
                value={local}
                onChange={(e) => setLocal(e.target.value)}
                onBlur={() => onBlur(local)}
                onKeyDown={(e) => e.key === 'Enter' && onBlur(local)}
                className="mt-1 w-full rounded-md border-line bg-paper text-sm focus:border-accent focus:ring-accent dark:border-line-dark dark:bg-paper-dark dark:text-ink-dark"
            />
        </div>
    );
}
