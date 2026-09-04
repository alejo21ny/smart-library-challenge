import { InputHTMLAttributes } from 'react';

export default function Checkbox({
    className = '',
    ...props
}: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-line text-accent shadow-sm focus:ring-accent dark:border-line-dark dark:bg-paper-dark dark:focus:ring-accent-dark dark:focus:ring-offset-paper-dark ' +
                className
            }
        />
    );
}
