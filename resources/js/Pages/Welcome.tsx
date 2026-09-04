import ThemeToggle from '@/Components/ThemeToggle';
import { Head, Link } from '@inertiajs/react';

export default function Welcome() {
    return (
        <div className="min-h-screen bg-paper text-ink dark:bg-paper-dark dark:text-ink-dark">
            <Head title="Smart Library" />

            <header className="mx-auto flex max-w-4xl items-center justify-between px-6 py-6">
                <span className="font-serif text-xl font-semibold">
                    Smart Library
                </span>
                <ThemeToggle />
            </header>

            <main className="mx-auto flex max-w-4xl flex-col items-start px-6 py-16 sm:py-24">
                <p className="text-sm font-medium uppercase tracking-wide text-accent dark:text-accent-dark">
                    A small, well-made library system
                </p>
                <h1 className="mt-4 max-w-xl font-serif text-4xl font-semibold leading-tight sm:text-5xl">
                    Catalog, borrow, and return — without the confusion.
                </h1>
                <p className="mt-5 max-w-lg text-ink-muted dark:text-ink-dark-muted">
                    Search the catalog, borrow and return books, and — if
                    you&apos;re not sure exactly what you&apos;re looking for —
                    ask the Library Assistant in plain language.
                </p>

                <div className="mt-8 flex flex-wrap gap-3">
                    <Link
                        href={route('login')}
                        className="rounded-lg bg-accent px-5 py-2.5 text-sm font-medium text-white hover:bg-accent-hover dark:bg-accent-dark dark:hover:bg-accent-dark-hover"
                    >
                        Log in
                    </Link>
                    <Link
                        href={route('register')}
                        className="rounded-lg border border-line px-5 py-2.5 text-sm font-medium text-ink-muted hover:border-accent hover:text-accent dark:border-line-dark dark:text-ink-dark-muted"
                    >
                        Create an account
                    </Link>
                </div>

                <p className="mt-10 text-xs text-ink-faint dark:text-ink-dark-faint">
                    Reviewing this project? See the README for demo accounts
                    (admin / librarian / member) — no setup required.
                </p>
            </main>
        </div>
    );
}
