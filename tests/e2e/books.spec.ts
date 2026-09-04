import { expect, test } from '@playwright/test';
import { DEMO_USERS, loginAs } from './helpers';

const BOOK_TITLE = `E2E Test Book ${Date.now()}`;
const EDITED_TITLE = `${BOOK_TITLE} (Edited)`;

test.describe.serial('admin book management', () => {
    test('admin can create a book', async ({ page }) => {
        await loginAs(page, DEMO_USERS.admin);

        await page.goto('/admin/books/create', { waitUntil: 'load' });
        await page.getByLabel('Title', { exact: false }).fill(BOOK_TITLE);
        await page.getByLabel('Author', { exact: false }).fill('E2E Author');
        await page.locator('form button:has-text("Add book")').click();

        await page.waitForURL('**/admin/books');
        // Scoped to the table cell — the flash message below the header
        // also contains this title, so a bare getByText() would be ambiguous.
        await expect(page.locator('td', { hasText: BOOK_TITLE })).toBeVisible();
    });

    test('admin can edit the book just created', async ({ page }) => {
        await loginAs(page, DEMO_USERS.admin);

        await page.goto('/admin/books', { waitUntil: 'load' });

        const row = page.locator('tr', { hasText: BOOK_TITLE }).first();
        await row.getByText('Edit', { exact: true }).click();
        await page.waitForURL(/\/admin\/books\/\d+\/edit/);

        await page.getByLabel('Title', { exact: false }).fill(EDITED_TITLE);
        await page.locator('form button:has-text("Save changes")').click();

        await page.waitForURL('**/admin/books');
        await expect(page.locator('td', { hasText: EDITED_TITLE })).toBeVisible();

        // Clean up so repeated runs don't accumulate books. Delete uses the
        // app's own confirmation Modal, not a native browser dialog.
        const editedRow = page.locator('tr', { hasText: EDITED_TITLE }).first();
        await editedRow.getByText('Delete', { exact: true }).click();
        await page.getByRole('button', { name: 'Remove book' }).click();
        await expect(page.locator('td', { hasText: EDITED_TITLE })).not.toBeVisible();
    });
});
