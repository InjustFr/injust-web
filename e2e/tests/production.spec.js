import { expect, test } from './support/test.js';
import { signIn } from './support/session.js';

const TITLE = 'E2E Service';
const NAME = 'E2E Producer';
const BODY = 'Hello, I would like this mix for my band please.';

async function setProduction(page, on) {
    await page.goto('/admin/settings');
    const toggle = page.getByRole('switch', { name: 'Production services' });
    await expect(toggle).toBeVisible();
    if ((await toggle.isChecked()) !== on) await toggle.click();
    if (on) await expect(toggle).toBeChecked();
    else await expect(toggle).not.toBeChecked();
}

test('the admin adds a quote service, the visitor requests a quote with it prefilled', async ({ page, visitorPage }) => {
    await signIn(page);
    await setProduction(page, true);

    await page.goto('/admin/production');
    await page.getByRole('button', { name: 'New service' }).first().click();
    const dialog = page.getByRole('dialog');
    await dialog.locator('input[name="title-en"]').fill(TITLE);
    await dialog.locator('input[name="title-fr"]').fill('Service E2E');
    await dialog.locator('[role="combobox"][name="pricing"]').click();
    await page.getByRole('option', { name: 'On quote' }).click();
    await dialog.locator('input[name="price"]').fill('250');
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.locator('.rows__item', { hasText: TITLE })).toBeVisible();

    await visitorPage.goto('/production');
    const card = visitorPage.locator('.service', { hasText: TITLE });
    await expect(card.locator('.offer-price')).toContainText('from €250');
    await card.getByRole('link', { name: 'Request a quote' }).click();
    await expect(visitorPage).toHaveURL(/\/contact\?topic=production&offer=/);
    await expect(visitorPage.locator('.contact-offer-name')).toHaveText(TITLE);

    await visitorPage.locator('#contact-name').fill(NAME);
    await visitorPage.locator('#contact-email').fill('producer@example.com');
    await visitorPage.locator('#contact-body').fill(BODY);
    await visitorPage.locator('.contact-send').click();
    await expect(visitorPage.locator('.contact-done')).toBeVisible();

    await page.goto('/admin/messages');
    const message = page.locator('.message', { hasText: NAME });
    await expect(message).toContainText(BODY);
    await expect(message).toContainText(TITLE);
    await message.getByRole('button', { name: 'Mark as handled' }).click();
    await expect(page.locator('.message', { hasText: NAME })).toHaveCount(0);

    await page.goto('/admin/production');
    const row = page.locator('.rows__item', { hasText: TITLE });
    await row.getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(page.locator('.rows__item', { hasText: TITLE })).toHaveCount(0);

    await setProduction(page, false);
});
