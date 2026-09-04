import { ButtonHTMLAttributes } from 'react';

export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center rounded-md border border-line bg-paper-raised px-4 py-2 text-xs font-semibold uppercase tracking-widest text-ink-muted shadow-sm transition duration-150 ease-in-out hover:bg-line/30 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 disabled:opacity-25 dark:border-line-dark dark:bg-paper-dark-raised dark:text-ink-dark-muted dark:hover:bg-line-dark/30 dark:focus:ring-offset-paper-dark ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
