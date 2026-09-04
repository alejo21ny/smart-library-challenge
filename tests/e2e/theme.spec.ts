import { expect, test } from '@playwright/test';
import { DEMO_USERS, loginAs } from './helpers';

test('light and dark theme both render the dashboard correctly', async ({ page }) => {
    await loginAs(page, DEMO_USERS.admin);

    await page.getByRole('radio', { name: 'Light' }).click();
    await expect(page.locator('html')).not.toHaveClass(/dark/);
    await expect(page.getByText('Total books')).toBeVisible();

    await page.getByRole('radio', { name: 'Dark' }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);
    await expect(page.getByText('Total books')).toBeVisible();
});
