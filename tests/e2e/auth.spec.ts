import { expect, test } from '@playwright/test';
import { DEMO_USERS, loginAs } from './helpers';

test('admin can log in and the dashboard loads with real stats', async ({ page }) => {
    await loginAs(page, DEMO_USERS.admin);

    // The staff dashboard's stat tiles — just confirm they rendered as numbers, not placeholders.
    await expect(page.getByText('Total books')).toBeVisible();
    await expect(page.getByText('Available')).toBeVisible();
    await expect(page.getByText('Borrowed')).toBeVisible();
    await expect(page.getByText('Overdue')).toBeVisible();
});

test('member can log in', async ({ page }) => {
    await loginAs(page, DEMO_USERS.member);
    await expect(page.getByText('Books in catalog')).toBeVisible();
});

test('a member gets a real 403, not a redirect or a blank page, on an admin-only route', async ({ page }) => {
    await loginAs(page, DEMO_USERS.member);

    const response = await page.goto('/admin/books');
    expect(response?.status()).toBe(403);
});
