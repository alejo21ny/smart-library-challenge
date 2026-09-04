export default function AvailabilityBadge({
    availability,
}: {
    availability: 'available' | 'borrowed';
}) {
    const isAvailable = availability === 'available';

    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${
                isAvailable
                    ? 'bg-success/10 text-success ring-success/30 dark:bg-success-dark/10 dark:text-success-dark dark:ring-success-dark/30'
                    : 'bg-line/50 text-ink-muted ring-line dark:bg-line-dark/50 dark:text-ink-dark-muted dark:ring-line-dark'
            }`}
        >
            {isAvailable ? 'Available' : 'Borrowed'}
        </span>
    );
}
