import { useTheme } from '@/contexts/ThemeContext';

const OPTIONS = [
    { value: 'light', label: 'Light', icon: '☀' },
    { value: 'dark', label: 'Dark', icon: '☾' },
    { value: 'system', label: 'System', icon: '◐' },
] as const;

export default function ThemeToggle() {
    const { theme, setTheme } = useTheme();

    return (
        <div
            role="radiogroup"
            aria-label="Color theme"
            className="inline-flex items-center gap-0.5 rounded-full border border-line bg-paper-raised p-0.5 dark:border-line-dark dark:bg-paper-dark-raised"
        >
            {OPTIONS.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    role="radio"
                    aria-checked={theme === option.value}
                    onClick={() => setTheme(option.value)}
                    className={`rounded-full px-2.5 py-1 text-xs font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent ${
                        theme === option.value
                            ? 'bg-ink text-paper dark:bg-ink-dark dark:text-paper-dark'
                            : 'text-ink-muted hover:text-ink dark:text-ink-dark-muted dark:hover:text-ink-dark'
                    }`}
                >
                    <span aria-hidden="true">{option.icon}</span>
                    <span className="sr-only">{option.label}</span>
                </button>
            ))}
        </div>
    );
}
