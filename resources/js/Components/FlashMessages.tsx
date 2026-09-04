import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function FlashMessages() {
    const { flash } = usePage().props;
    const [dismissed, setDismissed] = useState(false);

    useEffect(() => {
        setDismissed(false);
    }, [flash?.success, flash?.error]);

    if (dismissed || (!flash?.success && !flash?.error)) return null;

    const isError = Boolean(flash?.error);
    const message = flash?.error ?? flash?.success;

    return (
        <div
            role="status"
            aria-live="polite"
            className={`mx-auto mb-4 flex max-w-5xl items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm ${
                isError
                    ? 'border-danger/30 bg-danger/10 text-danger dark:border-danger-dark/40 dark:bg-danger-dark/10 dark:text-danger-dark'
                    : 'border-success/30 bg-success/10 text-success dark:border-success-dark/40 dark:bg-success-dark/10 dark:text-success-dark'
            }`}
        >
            <span>{message}</span>
            <button
                type="button"
                onClick={() => setDismissed(true)}
                aria-label="Dismiss message"
                className="shrink-0 rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent"
            >
                ✕
            </button>
        </div>
    );
}
