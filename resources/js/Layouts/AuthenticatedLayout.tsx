import FlashMessages from '@/Components/FlashMessages';
import ThemeToggle from '@/Components/ThemeToggle';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'librarian' | 'member';
}

function navItemsFor(role: AuthUser['role']) {
    const items = [
        {
            href: route('dashboard'),
            label: 'Dashboard',
            active: route().current('dashboard'),
        },
        {
            href: route('catalog.index'),
            label: 'Catalog',
            active: route().current('catalog.*'),
        },
    ];

    if (role === 'member') {
        items.push({
            href: route('loans.mine'),
            label: 'My Loans',
            active: route().current('loans.mine'),
        });
    }

    if (role === 'librarian' || role === 'admin') {
        items.push(
            {
                href: route('admin.books.index'),
                label: 'Manage Books',
                active: route().current('admin.books.*'),
            },
            {
                href: route('admin.loans.index'),
                label: 'Circulation',
                active: route().current('admin.loans.*'),
            },
        );
    }

    if (role === 'admin') {
        items.push({
            href: route('admin.users.index'),
            label: 'Users',
            active: route().current('admin.users.*'),
        });
    }

    items.push({
        href: route('assistant'),
        label: 'Assistant',
        active: route().current('assistant'),
    });

    return items;
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user as unknown as AuthUser;
    const [mobileOpen, setMobileOpen] = useState(false);
    const navItems = navItemsFor(user.role);

    return (
        <div className="min-h-screen bg-paper text-ink dark:bg-paper-dark dark:text-ink-dark">
            <header className="sticky top-0 z-30 border-b border-line bg-paper/90 backdrop-blur dark:border-line-dark dark:bg-paper-dark/90">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <Link
                        href={route('dashboard')}
                        className="shrink-0 font-serif text-xl font-semibold tracking-tight"
                    >
                        Smart Library
                    </Link>

                    <nav
                        aria-label="Primary"
                        className="hidden items-center gap-1 lg:flex"
                    >
                        {navItems.map((item) => (
                            <Link
                                key={item.label}
                                href={item.href}
                                className={`rounded-md px-3 py-2 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent ${
                                    item.active
                                        ? 'bg-ink text-paper dark:bg-ink-dark dark:text-paper-dark'
                                        : 'text-ink-muted hover:bg-line/50 hover:text-ink dark:text-ink-dark-muted dark:hover:bg-line-dark/50 dark:hover:text-ink-dark'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="hidden items-center gap-3 lg:flex">
                        <ThemeToggle />
                        <div className="flex items-center gap-2 rounded-full border border-line py-1 pl-3 pr-1 text-sm dark:border-line-dark">
                            <span className="text-ink-muted dark:text-ink-dark-muted">
                                {user.name}
                            </span>
                            <span className="rounded-full bg-accent/10 px-2 py-0.5 text-xs font-medium capitalize text-accent dark:bg-accent-dark/10 dark:text-accent-dark">
                                {user.role}
                            </span>
                        </div>
                        <Link
                            href={route('profile.edit')}
                            className="text-sm text-ink-muted hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent dark:text-ink-dark-muted dark:hover:text-ink-dark"
                        >
                            Profile
                        </Link>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink-muted hover:border-accent hover:text-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent dark:border-line-dark dark:text-ink-dark-muted dark:hover:border-accent-dark dark:hover:text-accent-dark"
                        >
                            Log out
                        </Link>
                    </div>

                    <button
                        type="button"
                        onClick={() => setMobileOpen((v) => !v)}
                        aria-expanded={mobileOpen}
                        aria-controls="mobile-nav"
                        className="rounded-md p-2 text-ink-muted hover:bg-line/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent dark:text-ink-dark-muted dark:hover:bg-line-dark/50 lg:hidden"
                    >
                        <span className="sr-only">Toggle menu</span>
                        <svg
                            className="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            {mobileOpen ? (
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            ) : (
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            )}
                        </svg>
                    </button>
                </div>

                {mobileOpen && (
                    <nav
                        id="mobile-nav"
                        aria-label="Primary mobile"
                        className="border-t border-line px-4 py-3 dark:border-line-dark lg:hidden"
                    >
                        <div className="flex flex-col gap-1">
                            {navItems.map((item) => (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    className={`rounded-md px-3 py-2 text-sm font-medium ${
                                        item.active
                                            ? 'bg-ink text-paper dark:bg-ink-dark dark:text-paper-dark'
                                            : 'text-ink-muted dark:text-ink-dark-muted'
                                    }`}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </div>
                        <div className="mt-3 flex items-center justify-between border-t border-line pt-3 dark:border-line-dark">
                            <ThemeToggle />
                            <div className="flex items-center gap-3 text-sm">
                                <Link
                                    href={route('profile.edit')}
                                    className="text-ink-muted dark:text-ink-dark-muted"
                                >
                                    Profile
                                </Link>
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    className="text-ink-muted dark:text-ink-dark-muted"
                                >
                                    Log out
                                </Link>
                            </div>
                        </div>
                    </nav>
                )}
            </header>

            {header && (
                <div className="border-b border-line bg-paper-raised dark:border-line-dark dark:bg-paper-dark-raised">
                    <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                        {header}
                    </div>
                </div>
            )}

            <main className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                <FlashMessages />
                {children}
            </main>
        </div>
    );
}
