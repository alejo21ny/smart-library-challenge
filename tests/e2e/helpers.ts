import { Page, expect } from '@playwright/test';

// Documented demo credentials (see README.md) — never a real secret.
export const DEMO_PASSWORD = 'password';

export const DEMO_USERS = {
    admin: 'admin@example.test',
    librarian: 'librarian@example.test',
    member: 'member@example.test',
    member2: 'member2@example.test',
} as const;

export async function loginAs(page: Page, email: string) {
    await page.goto('/login', { waitUntil: 'load' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(DEMO_PASSWORD);
    // PrimaryButton doesn't set an explicit type attribute (relies on the
    // browser's default submit behavior for the only button in a <form>).
    await page.locator('form button').click();
    // This project's local dev server (php artisan serve behind a Windows
    // bind mount — see README) gets measurably slower over a long test run;
    // give login specifically more room than the suite-wide default.
    await page.waitForURL('**/dashboard', { timeout: 60_000 });
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible({ timeout: 60_000 });
}
