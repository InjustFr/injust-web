import { expect, test } from './support/test.js';
import { signIn } from './support/session.js';

const NAME = 'E2E Visitor';
const BODY = 'Hello, I would like to book this lesson please.';

async function setLessons(page, on) {
    await page.goto('/admin/settings');
    const toggle = page.getByRole('switch', { name: 'Guitar lessons' });
    await expect(toggle).toBeVisible();
    if ((await toggle.isChecked()) !== on) await toggle.click();
    if (on) await expect(toggle).toBeChecked();
    else await expect(toggle).not.toBeChecked();
}

test('a visitor writes from a lesson Contact me button, the admin handles the message', async ({ page, visitorPage }) => {
    await signIn(page);
    await setLessons(page, true);

    await visitorPage.goto('/lessons');
    const offer = visitorPage.locator('.offer').first();
    const offerTitle = (await offer.locator('.offer-title').textContent()).trim();
    await offer.getByRole('link', { name: 'Contact me' }).click();
    await expect(visitorPage).toHaveURL(/\/contact\?topic=lessons&offer=/);
    await expect(visitorPage.locator('.contact-offer-name')).toHaveText(offerTitle);

    await visitorPage.locator('#contact-name').fill(NAME);
    await visitorPage.locator('#contact-email').fill('visitor@example.com');
    await visitorPage.locator('#contact-body').fill(BODY);
    await visitorPage.locator('.contact-send').click();
    await expect(visitorPage.locator('.contact-done')).toBeVisible();

    await page.goto('/admin/messages');
    const message = page.locator('.message', { hasText: NAME });
    await expect(message).toContainText(BODY);
    await expect(message).toContainText(offerTitle);
    await message.getByRole('button', { name: 'Mark as handled' }).click();
    await expect(page.locator('.message', { hasText: NAME })).toHaveCount(0);
    await page.getByRole('checkbox', { name: 'Show handled messages' }).check();
    await expect(page.locator('.message--handled', { hasText: NAME })).toBeVisible();

    await setLessons(page, false);
});

test('the honeypot stores nothing', async ({ page, visitorPage }) => {
    await visitorPage.goto('/contact');
    await visitorPage.locator('#contact-name').fill('Bot Honeypot');
    await visitorPage.locator('#contact-email').fill('bot@example.com');
    await visitorPage.locator('#contact-body').fill('Buy cheap things at my website now.');
    await visitorPage.locator('#contact-website').evaluate((input) => {
        input.value = 'http://spam.example';
        input.dispatchEvent(new Event('input'));
    });
    await visitorPage.locator('.contact-send').click();
    await expect(visitorPage.locator('.contact-done')).toBeVisible();

    await signIn(page);
    await page.goto('/admin/messages');
    await page.getByRole('checkbox', { name: 'Show handled messages' }).check();
    await expect(page.locator('.message').first()).toBeVisible();
    await expect(page.locator('.message', { hasText: 'Bot Honeypot' })).toHaveCount(0);
});
