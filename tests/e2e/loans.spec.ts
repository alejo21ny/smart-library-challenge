import { expect, test } from '@playwright/test';
import { DEMO_USERS, loginAs } from './helpers';

let borrowedBookUrl = '';
let borrowedBookTitle = '';

test.describe.serial('borrow, double-borrow protection, and return', () => {
    test('a member can borrow an available book', async ({ page }) => {
        await loginAs(page, DEMO_USERS.member2);

        await page.goto('/catalog?availability=available', { waitUntil: 'load' });
        const firstAvailable = page.locator('main a', { has: page.getByText('Available') }).first();
        borrowedBookTitle = (await firstAvailable.locator('h2, h3').first().textContent()) ?? '';

        // Inertia visits don't fire a full page 'load' event — wait for the
        // URL itself to change to the book-detail route instead.
        await firstAvailable.click();
        await page.waitForURL(/\/catalog\/\d+$/);
        borrowedBookUrl = page.url();

        await expect(page.getByText('Available', { exact: true })).toBeVisible();
        const borrowButton = page.locator('button:has-text("Borrow this book")');
        await borrowButton.click();
        await borrowButton.waitFor({ state: 'detached' });

        await expect(page.getByText('Borrowed', { exact: true })).toBeVisible();
    });

    test('a second member cannot borrow the same book while it is out', async ({ page }) => {
        test.skip(!borrowedBookUrl, 'Depends on the previous test having borrowed a book.');

        await loginAs(page, DEMO_USERS.member);
        await page.goto(borrowedBookUrl, { waitUntil: 'load' });

        await expect(page.locator('button:has-text("Borrow this book")')).toHaveCount(0);
        await expect(page.getByText('Borrowed', { exact: true })).toBeVisible();
    });

    test('the borrower can return the book, making it available again', async ({ page }) => {
        test.skip(!borrowedBookUrl, 'Depends on the first test having borrowed a book.');

        await loginAs(page, DEMO_USERS.member2);
        await page.goto('/my-loans', { waitUntil: 'load' });

        const row = page.locator('li', { hasText: borrowedBookTitle }).first();
        const returnButton = row.getByText('Return', { exact: true });
        await returnButton.click();
        await returnButton.waitFor({ state: 'detached' });

        await page.goto(borrowedBookUrl, { waitUntil: 'load' });
        await expect(page.getByText('Available', { exact: true })).toBeVisible();
        await expect(page.locator('button:has-text("Borrow this book")')).toBeVisible();
    });
});
