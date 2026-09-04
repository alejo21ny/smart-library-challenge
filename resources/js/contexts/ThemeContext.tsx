import {
    createContext,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';

type ThemePreference = 'light' | 'dark' | 'system';

interface ThemeContextValue {
    theme: ThemePreference;
    setTheme: (theme: ThemePreference) => void;
    resolvedTheme: 'light' | 'dark';
}

const ThemeContext = createContext<ThemeContextValue | null>(null);

function getSystemPrefersDark(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-color-scheme: dark)').matches
    );
}

function readStoredTheme(): ThemePreference {
    if (typeof window === 'undefined') return 'system';
    const stored = window.localStorage.getItem('theme');
    return stored === 'light' || stored === 'dark' ? stored : 'system';
}

export function ThemeProvider({ children }: { children: ReactNode }) {
    const [theme, setThemeState] = useState<ThemePreference>(readStoredTheme);
    const [systemPrefersDark, setSystemPrefersDark] =
        useState(getSystemPrefersDark);

    useEffect(() => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const listener = (e: MediaQueryListEvent) =>
            setSystemPrefersDark(e.matches);
        media.addEventListener('change', listener);
        return () => media.removeEventListener('change', listener);
    }, []);

    const resolvedTheme: 'light' | 'dark' = useMemo(() => {
        if (theme === 'system') return systemPrefersDark ? 'dark' : 'light';
        return theme;
    }, [theme, systemPrefersDark]);

    useEffect(() => {
        document.documentElement.classList.toggle(
            'dark',
            resolvedTheme === 'dark',
        );
    }, [resolvedTheme]);

    function setTheme(next: ThemePreference) {
        setThemeState(next);
        if (next === 'system') {
            window.localStorage.removeItem('theme');
        } else {
            window.localStorage.setItem('theme', next);
        }
    }

    return (
        <ThemeContext.Provider value={{ theme, setTheme, resolvedTheme }}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme(): ThemeContextValue {
    const ctx = useContext(ThemeContext);
    if (!ctx) throw new Error('useTheme must be used within a ThemeProvider');
    return ctx;
}
