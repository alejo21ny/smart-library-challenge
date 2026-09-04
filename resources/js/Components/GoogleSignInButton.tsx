export default function GoogleSignInButton() {
    return (
        <div className="mb-5">
            <a
                href={route('auth.google.redirect')}
                className="flex w-full items-center justify-center gap-2 rounded-lg border border-line bg-paper px-4 py-2.5 text-sm font-medium text-ink transition hover:border-accent hover:text-accent dark:border-line-dark dark:bg-paper-dark dark:text-ink-dark dark:hover:border-accent-dark dark:hover:text-accent-dark"
            >
                <GoogleIcon />
                Continue with Google
            </a>
            <div className="mt-5 flex items-center gap-3 text-xs text-ink-faint dark:text-ink-dark-faint">
                <span className="h-px flex-1 bg-line dark:bg-line-dark" />
                or continue with email
                <span className="h-px flex-1 bg-line dark:bg-line-dark" />
            </div>
        </div>
    );
}

function GoogleIcon() {
    return (
        <svg className="h-4 w-4" viewBox="0 0 48 48" aria-hidden="true">
            <path
                fill="#FFC107"
                d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.6-6 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l5.7-5.7C34.5 6 29.5 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.2-.1-2.4-.4-3.5z"
            />
            <path
                fill="#FF3D00"
                d="M6.3 14.7l6.6 4.8C14.6 15.9 18.9 13 24 13c3 0 5.8 1.1 7.9 3l5.7-5.7C34.5 6 29.5 4 24 4c-7.5 0-14 4.2-17.7 10.7z"
            />
            <path
                fill="#4CAF50"
                d="M24 44c5.4 0 10.3-1.9 14.1-5.1l-6.5-5.5C29.4 35 26.8 36 24 36c-5.3 0-9.7-3.4-11.3-8l-6.6 5.1C9.9 39.6 16.4 44 24 44z"
            />
            <path
                fill="#1976D2"
                d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.2 4.2-4.1 5.5l6.5 5.5C41.5 35.6 44 30.2 44 24c0-1.2-.1-2.4-.4-3.5z"
            />
        </svg>
    );
}
