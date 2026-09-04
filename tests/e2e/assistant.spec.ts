import { expect, test } from '@playwright/test';
import { DEMO_USERS, loginAs } from './helpers';

async function ask(page: import('@playwright/test').Page, query: string) {
    await page.locator('#assistant-query').fill(query);
    await Promise.all([
        page.waitForResponse((r) => r.url().includes('/assistant/query') && r.request().method() === 'POST', {
            timeout: 60_000,
        }),
        page.locator('button:has-text("Ask")').click(),
    ]);
}

test('the assistant returns a grounded result with no AI key configured', async ({ page }) => {
    await loginAs(page, DEMO_USERS.member);

    await page.goto('/assistant', { waitUntil: 'load' });
    await ask(page, 'available laravel book');

    await expect(page.getByText('Assistant response').first()).toBeVisible();
    // Real catalog cards, not a fabricated answer — see LibraryTools/ARCHITECTURE.md.
    await expect(page.locator('main a', { hasText: 'Laravel' }).first()).toBeVisible();
});

test('a misspelled, truncated query still finds the real book via the fuzzy fallback', async ({ page }) => {
    await loginAs(page, DEMO_USERS.member);

    await page.goto('/assistant', { waitUntil: 'load' });
    await ask(page, 'arquitecture clea');

    await expect(page.getByText('Assistant response').first()).toBeVisible();
    await expect(page.locator('main').getByText('Clean Architecture').first()).toBeVisible();
});
