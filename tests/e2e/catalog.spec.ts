import { expect, test } from '@playwright/test';
import { DEMO_USERS, loginAs } from './helpers';

test('catalog search returns real, relevant results', async ({ page }) => {
    await loginAs(page, DEMO_USERS.member);

    await page.goto('/catalog?q=Laravel', { waitUntil: 'load' });
    await expect(page.locator('main a', { hasText: 'Laravel' }).first()).toBeVisible();
});
