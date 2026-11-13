import { expect, test } from './support/test.js';
import { OUTSIDER, signIn, signInWithAccount } from './support/session.js';

test('the home page needs no account, the admin does', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('INJUST');

    await page.goto('/admin/');
    await expect(page.locator('input[name="username"]')).toBeVisible();
});

test('an editor signs in with their Injust account and signs out', async ({ page }) => {
    await signIn(page);
    await expect(page.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeVisible();

    await page.getByRole('button', { name: 'Sign out' }).click();
    await page.waitForURL(/endsession|\/$/);
});

test('an account outside the injust group is turned away', async ({ page }) => {
    await page.goto('/admin/');
    await signInWithAccount(page, OUTSIDER);
    await expect(page.getByRole('alert')).toContainText('does not have access');
    await expect(page.getByRole('link', { name: 'Back to the site' })).toBeVisible();
});
