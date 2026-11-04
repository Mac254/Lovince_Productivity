import { expect, test, type Page } from '@playwright/test';
import { owner } from '../playwright.config';

async function signIn(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(owner.email);
    await page.getByLabel('Password', { exact: true }).fill(owner.password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
}

test('signs in and out', async ({ page }) => {
    await signIn(page);
    await page.getByRole('button', { name: /^Account menu/ }).click();
    await page.getByRole('menuitem', { name: 'Sign out' }).click();
    await expect(page).toHaveURL(/\/login/);

    await page.goto('/tasks');
    await expect(page).toHaveURL(/\/login/);
});

test('adds a task and completes it', async ({ page }) => {
    await signIn(page);
    await page.getByRole('button', { name: 'New task' }).first().click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Title').fill('Write the smoke tests');
    await dialog.getByRole('button', { name: /^(Create|Add|Save)/ }).click();
    await expect(dialog).toBeHidden();

    await page.goto('/tasks');
    await page.getByRole('button', { name: 'Mark “Write the smoke tests” complete' }).click();
    await expect(page.getByText('Completed “Write the smoke tests”')).toBeVisible();
});

test('saves a journal entry', async ({ page }) => {
    await signIn(page);
    await page.goto('/journal');
    await page.locator('#journal-body').fill('Smoke tests are green.');
    await expect(page.getByText(/^Saved/)).toBeVisible({ timeout: 10_000 });

    await page.reload();
    await expect(page.locator('#journal-body')).toHaveValue('Smoke tests are green.');
});

test('runs and pauses the focus timer', async ({ page }) => {
    await signIn(page);
    await page.goto('/focus?mode=timer');
    await page.getByRole('button', { name: 'Start', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Pause', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Pause', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Resume', exact: true })).toBeVisible();
});
