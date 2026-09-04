import ThemeToggle from '@/Components/ThemeToggle';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-paper pt-6 text-ink dark:bg-paper-dark dark:text-ink-dark sm:justify-center sm:pt-0">
            <div className="flex w-full max-w-md items-center justify-between px-6 sm:px-0">
                <Link href="/" className="font-serif text-lg font-semibold">
                    Smart Library
                </Link>
                <ThemeToggle />
            </div>

            <div className="mt-6 w-full overflow-hidden border border-line bg-paper-raised px-6 py-6 shadow-sm dark:border-line-dark dark:bg-paper-dark-raised sm:max-w-md sm:rounded-lg">
                {children}
            </div>
        </div>
    );
}
