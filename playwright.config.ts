import { defineConfig, devices } from '@playwright/test';

/**
 * Formal E2E suite for the reviewer-critical flows (see docs/DEPLOYMENT.md
 * and ARCHITECTURE.md for what this complements — Pest covers domain logic,
 * this covers actual browser behavior). Assumes the app is already running
 * locally via `docker compose up -d` with demo data seeded — this suite
 * does not start or seed the app itself. Run with `npm run test:e2e`.
 */
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false, // shared demo dataset — flows can interact (borrow/return, book create/edit)
    forbidOnly: !!process.env.CI,
    // The local dev server (php artisan serve behind Sail on Windows bind
    // mounts — see README's Windows/Docker notes) is measurably slower and
    // less consistent than a real deployment; one retry absorbs that without
    // masking a real, reproducible failure (which fails again on retry).
    retries: 1,
    workers: 1,
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
    timeout: 120_000,
    expect: { timeout: 30_000 },
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost',
        actionTimeout: 30_000,
        navigationTimeout: 45_000,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'off',
    },
    projects: [
        {
            name: 'desktop-chromium',
            use: { ...devices['Desktop Chrome'] },
            testIgnore: /mobile\.spec\.ts/,
        },
        {
            name: 'mobile-chromium',
            use: { ...devices['Pixel 7'] },
            testMatch: /mobile\.spec\.ts/,
        },
    ],
});
