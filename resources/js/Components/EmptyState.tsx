export default function EmptyState({
    title,
    body,
}: {
    title: string;
    body?: string;
}) {
    return (
        <div className="rounded-lg border border-dashed border-line px-6 py-10 text-center dark:border-line-dark">
            <p className="text-sm font-medium text-ink-muted dark:text-ink-dark-muted">
                {title}
            </p>
            {body && (
                <p className="mt-1 text-xs text-ink-faint dark:text-ink-dark-faint">
                    {body}
                </p>
            )}
        </div>
    );
}
