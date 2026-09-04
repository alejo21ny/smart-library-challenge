import { expect, test } from '@playwright/test';
import { DEMO_USERS, loginAs } from './helpers';

test('mobile navigation opens and links to the catalog', async ({ page }) => {
    await loginAs(page, DEMO_USERS.member);

    const toggle = page.getByRole('button', { name: 'Toggle menu' });
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');

    const mobileNav = page.getByRole('navigation', { name: 'Primary mobile' });
    await expect(mobileNav).toBeVisible();

    await mobileNav.getByRole('link', { name: 'Catalog' }).click();
    await page.waitForURL('**/catalog');
    await expect(page.getByRole('heading', { name: 'Catalog' })).toBeVisible();
});
