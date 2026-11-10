import { expect, test } from './support/test.js';
import { signIn } from './support/session.js';

async function save(page, formField) {
    await formField.locator('xpath=ancestor::form').getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Saved.').first()).toBeVisible();
}

test('a section is hidden until the admin switches it on, then hidden again', async ({ page, visitorPage }) => {
    const response = await page.goto('/lessons');
    expect(response.status()).toBe(404);
    await expect(page.locator('a.nav-section[href="/lessons"]')).toHaveCount(0);

    await signIn(page);
    await page.goto('/admin/settings');
    await page.getByRole('switch', { name: 'Guitar lessons' }).click();
    await expect(page.getByRole('switch', { name: 'Guitar lessons' })).toBeChecked();

    await visitorPage.goto('/');
    await expect(visitorPage.locator('a.nav-section[href="/lessons"]')).toBeVisible();
    expect((await visitorPage.goto('/lessons')).status()).toBe(200);

    await page.getByRole('switch', { name: 'Guitar lessons' }).click();
    await expect(page.getByRole('switch', { name: 'Guitar lessons' })).not.toBeChecked();
    expect((await visitorPage.goto('/lessons')).status()).toBe(404);
});

test('the legal notice shows the identity typed in the admin', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/settings');
    const name = page.locator('input[name="name"]');
    await name.fill('Louis Testeur');
    await page.locator('input[name="siren"]').fill('732829320');
    await page.locator('input[name="address"]').fill('1 rue du Test, 75001 Paris');
    await save(page, name);

    await page.goto('/legal/mentions');
    await expect(page.locator('.legal-body')).toContainText('Louis Testeur');
    await expect(page.locator('.legal-body')).toContainText('732829320');
    await expect(page.locator('.legal-body')).not.toContainText('{name}');
});

test('the language switch moves between EN and FR', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await page.locator('a.lang-switch').click();
    await expect(page).toHaveURL(/\/fr\/?$/);
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    await page.locator('a.lang-switch').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
});
